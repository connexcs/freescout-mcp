<?php

namespace Modules\McpServer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\McpServer\OAuth\ClientMetadataValidator;
use Modules\McpServer\OAuth\ClientResolver;
use Modules\McpServer\OAuth\OAuthException;
use Modules\McpServer\OAuth\OAuthServerConfiguration;
use Modules\McpServer\OAuth\OAuthTokenService;

final class OAuthController extends Controller
{
    private $clients;
    private $validator;
    private $tokens;
    private $configuration;

    public function __construct(
        ClientResolver $clients,
        ClientMetadataValidator $validator,
        OAuthTokenService $tokens,
        OAuthServerConfiguration $configuration
    ) {
        $this->clients = $clients;
        $this->validator = $validator;
        $this->tokens = $tokens;
        $this->configuration = $configuration;
    }

    public function register(Request $request)
    {
        $this->requireEnabled();
        if (!filter_var(config('mcpserver.oauth.dynamic_registration_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            abort(404);
        }
        try {
            $payload = $request->json()->all();
            if (!is_array($payload)) {
                throw new OAuthException('invalid_client_metadata', 'A JSON client metadata object is required.');
            }
            $client = $this->clients->register($payload);
            $metadata = $this->clients->metadata($client);
            $metadata['client_id_issued_at'] = $client->created_at->getTimestamp();

            return response()->json($metadata, 201)->header('Cache-Control', 'no-store');
        } catch (OAuthException $exception) {
            return $this->error($exception);
        }
    }

    public function authorization(Request $request)
    {
        $this->requireEnabled();
        try {
            if ('code' !== $request->query('response_type')) {
                throw new OAuthException('unsupported_response_type', 'Only response_type=code is supported.');
            }
            $clientId = (string) $request->query('client_id', '');
            $redirectUri = (string) $request->query('redirect_uri', '');
            $resource = (string) $request->query('resource', '');
            $challenge = (string) $request->query('code_challenge', '');
            if ('' === $clientId || '' === $redirectUri || '' === $resource || '' === $challenge
                || 'S256' !== $request->query('code_challenge_method')
                || 1 !== preg_match('/\A[A-Za-z0-9_-]{43}\z/', $challenge)
                || !hash_equals($this->configuration->resource(), $resource)
            ) {
                throw new OAuthException('invalid_request', 'client_id, exact MCP resource, redirect_uri, and S256 PKCE are required.');
            }
            $client = $this->clients->resolve($clientId);
            $metadata = $this->clients->metadata($client);
            $this->validator->assertExactRedirect($metadata, $redirectUri);
            $scopes = $this->tokens->validateScopes($request->query('scope'));
            $state = $request->query('state');
            if (null !== $state && (!is_string($state) || strlen($state) > 512)) {
                throw new OAuthException('invalid_request', 'state is too long.');
            }

            $handle = bin2hex(random_bytes(24));
            $request->session()->put('mcpserver_oauth.'.$handle, [
                'client_record_id' => (int) $client->id,
                'client_id' => $clientId,
                'client_name' => $client->client_name,
                'redirect_uri' => $redirectUri,
                'resource' => $resource,
                'scopes' => $scopes,
                'code_challenge' => $challenge,
                'state' => $state,
                'created_at' => time(),
            ]);

            return view('mcpserver::oauth.consent', [
                'handle' => $handle,
                'clientName' => $client->client_name,
                'clientId' => $clientId,
                'redirectHost' => (string) parse_url($redirectUri, PHP_URL_HOST),
                'loopback' => in_array(strtolower((string) parse_url($redirectUri, PHP_URL_HOST)), ['localhost', '127.0.0.1', '::1'], true),
                'scopes' => $scopes,
            ]);
        } catch (OAuthException $exception) {
            return $this->browserError($exception);
        }
    }

    public function decide(Request $request)
    {
        $this->requireEnabled();
        $handle = (string) $request->input('request_handle', '');
        $key = 'mcpserver_oauth.'.$handle;
        $pending = $request->session()->pull($key);
        if (!is_array($pending) || ($pending['created_at'] ?? 0) < time() - 600) {
            return $this->browserError(new OAuthException('invalid_request', 'This authorization request expired.'));
        }

        $params = ['iss' => $this->configuration->issuer()];
        if (null !== ($pending['state'] ?? null)) {
            $params['state'] = $pending['state'];
        }
        if ('approve' !== $request->input('decision')) {
            $params['error'] = 'access_denied';
            $params['error_description'] = 'The user denied the authorization request.';

            return redirect()->away($this->appendQuery($pending['redirect_uri'], $params));
        }

        $client = \Modules\McpServer\Entities\McpOAuthClient::find($pending['client_record_id']);
        if (null === $client || !hash_equals((string) $client->client_id, (string) $pending['client_id'])) {
            return $this->browserError(new OAuthException('invalid_request', 'The OAuth client is no longer available.'));
        }
        $params['code'] = $this->tokens->issueCode(
            auth()->user(), $client, $pending['redirect_uri'], $pending['resource'], $pending['scopes'], $pending['code_challenge']
        );

        return redirect()->away($this->appendQuery($pending['redirect_uri'], $params));
    }

    public function token(Request $request)
    {
        $this->requireEnabled();
        try {
            $grant = (string) $request->input('grant_type', '');
            $clientId = (string) $request->input('client_id', '');
            $resource = (string) $request->input('resource', '');
            if ('' === $clientId || '' === $resource || !hash_equals($this->configuration->resource(), $resource)) {
                throw new OAuthException('invalid_request', 'client_id and the exact MCP resource are required.');
            }
            $this->clients->resolve($clientId);
            if ('authorization_code' === $grant) {
                $result = $this->tokens->exchangeCode(
                    (string) $request->input('code', ''), $clientId,
                    (string) $request->input('redirect_uri', ''), $resource,
                    (string) $request->input('code_verifier', '')
                );
            } elseif ('refresh_token' === $grant) {
                $result = $this->tokens->refresh(
                    (string) $request->input('refresh_token', ''), $clientId, $resource,
                    $request->has('scope') ? (string) $request->input('scope') : null
                );
            } else {
                throw new OAuthException('unsupported_grant_type', 'Supported grants are authorization_code and refresh_token.');
            }

            return response()->json($result)->header('Cache-Control', 'no-store')->header('Pragma', 'no-cache');
        } catch (OAuthException $exception) {
            return $this->error($exception);
        }
    }

    public function revoke(Request $request)
    {
        $this->requireEnabled();
        $this->tokens->revoke((string) $request->input('token', ''));

        return response('', 200)->header('Cache-Control', 'no-store');
    }

    private function error(OAuthException $exception)
    {
        return response()->json(['error' => $exception->error, 'error_description' => $exception->getMessage()], 400)
            ->header('Cache-Control', 'no-store')->header('Pragma', 'no-cache');
    }

    private function browserError(OAuthException $exception)
    {
        return response()->view('mcpserver::oauth.error', ['error' => $exception], 400)
            ->header('Cache-Control', 'no-store');
    }

    /** @param array<string, string> $params */
    private function appendQuery(string $uri, array $params): string
    {
        return $uri.(false === strpos($uri, '?') ? '?' : '&').http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function requireEnabled(): void
    {
        if (!$this->configuration->enabled() || !config('mcpserver.enabled', false)) {
            abort(404);
        }
    }
}
