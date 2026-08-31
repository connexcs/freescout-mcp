<?php

namespace Modules\McpServer\OAuth;

final class OAuthServerConfiguration
{
    public function enabled(): bool
    {
        if (!filter_var(config('mcpserver.oauth.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }
        $parts = parse_url($this->issuer());
        if (false === $parts || empty($parts['host']) || isset($parts['query'], $parts['fragment'], $parts['user'], $parts['pass'])
            || ('https' !== strtolower((string) ($parts['scheme'] ?? '')) && !in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '::1'], true))
        ) {
            return false;
        }
        if (class_exists(\App\Option::class)) {
            return filter_var(\App\Option::get('mcpserver.oauth_enabled', true), FILTER_VALIDATE_BOOLEAN);
        }

        return true;
    }

    public function issuer(): string
    {
        return rtrim((string) config('mcpserver.oauth.issuer', config('app.url')), '/');
    }

    public function resource(): string
    {
        return $this->publicUrl('/mcp');
    }

    public function protectedResourceMetadataUrl(): string
    {
        return $this->publicUrl('/.well-known/oauth-protected-resource');
    }

    /** @return string[] */
    public function supportedScopes(): array
    {
        return ['mcp:read', 'mcp:write', 'offline_access'];
    }

    /** @return array<string, mixed> */
    public function authorizationServerMetadata(): array
    {
        $metadata = [
            'issuer' => $this->issuer(),
            'authorization_endpoint' => $this->publicUrl('/mcp/oauth/authorize'),
            'token_endpoint' => $this->publicUrl('/mcp/oauth/token'),
            'revocation_endpoint' => $this->publicUrl('/mcp/oauth/revoke'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => $this->supportedScopes(),
            'client_id_metadata_document_supported' => true,
            'authorization_response_iss_parameter_supported' => true,
        ];
        if (filter_var(config('mcpserver.oauth.dynamic_registration_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $metadata['registration_endpoint'] = $this->publicUrl('/mcp/oauth/register');
        }

        return $metadata;
    }

    /** @return array<string, mixed> */
    public function protectedResourceMetadata(): array
    {
        return [
            'resource' => $this->resource(),
            'authorization_servers' => [$this->issuer()],
            'scopes_supported' => ['mcp:read', 'mcp:write'],
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'FreeScout MCP Server',
        ];
    }

    private function publicUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }
}
