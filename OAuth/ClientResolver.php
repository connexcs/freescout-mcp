<?php

namespace Modules\McpServer\OAuth;

use Carbon\Carbon;
use Modules\McpServer\Entities\McpOAuthClient;

final class ClientResolver
{
    private $validator;

    public function __construct(ClientMetadataValidator $validator)
    {
        $this->validator = $validator;
    }

    public function resolve(string $clientId): McpOAuthClient
    {
        $record = McpOAuthClient::where('client_id_hash', hash('sha256', $clientId))->first();
        if (null !== $record && !hash_equals((string) $record->client_id, $clientId)) {
            throw new OAuthException('invalid_client', 'Unknown client_id.');
        }
        if (null !== $record && ('cimd' !== $record->source || $this->cacheIsFresh($record))) {
            return $record;
        }
        if (!$this->validMetadataUrl($clientId)) {
            throw new OAuthException('invalid_client', 'Unknown client_id.');
        }

        $metadata = $this->validator->validate($this->fetch($clientId), $clientId);

        return $this->store($metadata, 'cimd', $record);
    }

    /** @param array<string, mixed> $metadata */
    public function register(array $metadata): McpOAuthClient
    {
        $metadata['client_id'] = 'fsmcp_client_'.rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $metadata = $this->validator->validate($metadata);

        return $this->store($metadata, 'dcr');
    }

    /** @param array<string, mixed> $metadata */
    public function metadata(McpOAuthClient $client): array
    {
        $value = is_array($client->metadata_json) ? $client->metadata_json : [];
        $value['client_id'] = $client->client_id;
        $value['client_name'] = $client->client_name;
        $value['redirect_uris'] = is_array($client->redirect_uris) ? $client->redirect_uris : [];

        return $value;
    }

    /** @param array<string, mixed> $metadata */
    private function store(array $metadata, string $source, ?McpOAuthClient $record = null): McpOAuthClient
    {
        $record = $record ?? new McpOAuthClient();
        $record->client_id = $metadata['client_id'];
        $record->client_id_hash = hash('sha256', $metadata['client_id']);
        $record->client_name = $metadata['client_name'];
        $record->redirect_uris = $metadata['redirect_uris'];
        $record->application_type = in_array(($metadata['application_type'] ?? 'web'), ['web', 'native'], true)
            ? $metadata['application_type'] ?? 'web'
            : 'web';
        $record->source = $source;
        $record->metadata_json = $metadata;
        $record->last_seen_at = Carbon::now();
        $record->save();

        return $record;
    }

    private function cacheIsFresh(McpOAuthClient $client): bool
    {
        $seconds = max(60, (int) config('mcpserver.oauth.client_metadata_cache_seconds', 3600));

        return null !== $client->last_seen_at && $client->last_seen_at->getTimestamp() > time() - $seconds;
    }

    private function validMetadataUrl(string $url): bool
    {
        if (strlen($url) > 2048 || 1 === preg_match('/[\x00-\x20\x7f]/', $url)) {
            return false;
        }
        $parts = parse_url($url);

        return false !== $parts
            && 'https' === strtolower((string) ($parts['scheme'] ?? ''))
            && !empty($parts['host'])
            && !isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            && !isset($parts['port'])
            && isset($parts['path'])
            && '/' !== $parts['path']
            && false === strpos($parts['path'], '/../')
            && false === strpos($parts['path'], '/./');
    }

    /** @return array<string, mixed> */
    private function fetch(string $url): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            throw new OAuthException('invalid_client', 'IP-literal client metadata URLs are not accepted.');
        }

        $answers = dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];
        foreach (is_array($answers) ? $answers : [] as $answer) {
            $ip = $answer['ip'] ?? $answer['ipv6'] ?? null;
            if (!is_string($ip) || false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new OAuthException('invalid_client', 'Client metadata resolved to a non-public address.');
            }
            $addresses[] = false !== strpos($ip, ':') ? '['.$ip.']' : $ip;
        }
        if ([] === $addresses) {
            throw new OAuthException('invalid_client', 'Client metadata hostname could not be resolved.');
        }

        // curl keys CURLOPT_RESOLVE entries by "host:port", so one entry per
        // address makes the last record win instead of adding a fallback. A host
        // with both A and AAAA records is therefore pinned to the AAAA address
        // alone, which fails outright on IPv4-only servers. All addresses have to
        // ride in a single comma-separated entry so curl can fall back across them.
        $resolve = [$host.':443:'.implode(',', $addresses)];

        $body = '';
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => $resolve,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'FreeScout-MCP/0.5',
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body) {
                if (strlen($body) + strlen($chunk) > 65536) {
                    return 0;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if (false === $ok || 200 !== $status) {
            throw new OAuthException('invalid_client', 'Client metadata could not be retrieved.');
        }
        try {
            $metadata = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new OAuthException('invalid_client', 'Client metadata is not valid JSON.');
        }
        if (!is_array($metadata)) {
            throw new OAuthException('invalid_client', 'Client metadata must be a JSON object.');
        }

        return $metadata;
    }
}
