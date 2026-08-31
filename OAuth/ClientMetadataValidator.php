<?php

namespace Modules\McpServer\OAuth;

final class ClientMetadataValidator
{
    /** @param array<string, mixed> $metadata @return array<string, mixed> */
    public function validate(array $metadata, ?string $expectedClientId = null): array
    {
        $clientId = $expectedClientId ?? (isset($metadata['client_id']) && is_string($metadata['client_id']) ? $metadata['client_id'] : '');
        $name = isset($metadata['client_name']) && is_string($metadata['client_name']) ? trim($metadata['client_name']) : '';
        $redirects = $metadata['redirect_uris'] ?? null;

        if (null !== $expectedClientId && (!isset($metadata['client_id']) || !is_string($metadata['client_id']) || !hash_equals($expectedClientId, $metadata['client_id']))) {
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
        if (isset($metadata['response_types']) && (!is_array($metadata['response_types']) || !in_array('code', $metadata['response_types'], true))) {
            throw new OAuthException('invalid_client_metadata', 'The code response type is required.');
        }
        if (isset($metadata['application_type']) && !in_array($metadata['application_type'], ['web', 'native'], true)) {
            throw new OAuthException('invalid_client_metadata', 'application_type must be web or native.');
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
        $metadata['grant_types'] = $metadata['grant_types'] ?? ['authorization_code', 'refresh_token'];
        $metadata['response_types'] = $metadata['response_types'] ?? ['code'];
        $metadata['application_type'] = $metadata['application_type'] ?? 'web';

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
        if (1 === preg_match('/[\x00-\x20\x7f]/', $uri)) {
            return false;
        }
        $parts = parse_url($uri);
        if (false === $parts || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass']) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        return 'https' === $scheme || ('http' === $scheme && in_array($host, ['localhost', '127.0.0.1', '::1'], true));
    }
}
