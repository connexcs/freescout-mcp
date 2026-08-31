<?php

namespace Modules\McpServer\OAuth;

final class ClientMetadataValidator
{
    /** @param array<string, mixed> $metadata @return array<string, mixed> */
    public function validate(array $metadata, ?string $expectedClientId = null): array
    {
        $clientId = $expectedClientId ?? (isset($metadata['client_id']) ? (string) $metadata['client_id'] : '');
        $name = isset($metadata['client_name']) ? trim((string) $metadata['client_name']) : '';
        $redirects = $metadata['redirect_uris'] ?? null;

        if (null !== $expectedClientId && (!isset($metadata['client_id']) || !hash_equals($expectedClientId, (string) $metadata['client_id']))) {
            throw new OAuthException('invalid_client', 'The metadata client_id does not match its document URL.');
        }
        if ('' === $clientId || '' === $name || strlen($name) > 150 || !is_array($redirects) || [] === $redirects) {
            throw new OAuthException('invalid_client_metadata', 'client_name and at least one redirect_uri are required.');
        }
        if (($metadata['token_endpoint_auth_method'] ?? 'none') !== 'none') {
            throw new OAuthException('invalid_client_metadata', 'Only public clients using token_endpoint_auth_method none are supported.');
        }
        if (isset($metadata['grant_types']) && (!is_array($metadata['grant_types']) || !in_array('authorization_code', $metadata['grant_types'], true))) {
            throw new OAuthException('invalid_client_metadata', 'The authorization_code grant is required.');
        }

        $validated = [];
        foreach ($redirects as $redirect) {
            if (!is_string($redirect) || !$this->validRedirectUri($redirect)) {
                throw new OAuthException('invalid_redirect_uri', 'Redirect URIs must use HTTPS or loopback HTTP without fragments.');
            }
            $validated[] = $redirect;
        }

        $metadata['client_id'] = $clientId;
        $metadata['client_name'] = $name;
        $metadata['redirect_uris'] = array_values(array_unique($validated));
        $metadata['token_endpoint_auth_method'] = 'none';

        return $metadata;
    }

    public function assertExactRedirect(array $metadata, string $redirectUri): void
    {
        if (!in_array($redirectUri, $metadata['redirect_uris'] ?? [], true)) {
            throw new OAuthException('invalid_request', 'redirect_uri is not registered for this client.');
        }
    }

    private function validRedirectUri(string $uri): bool
    {
        $parts = parse_url($uri);
        if (false === $parts || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass']) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        return 'https' === $scheme || ('http' === $scheme && in_array($host, ['localhost', '127.0.0.1', '::1'], true));
    }
}
