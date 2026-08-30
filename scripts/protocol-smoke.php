<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server\Stateless\RequestMeta;
use Modules\McpServer\Services\McpServerFactory;

$protocol = (new McpServerFactory())->build();
$method = 'server/discover';
$payload = json_encode([
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => $method,
    'params' => [
        '_meta' => [
            RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value,
            RequestMeta::CLIENT_CAPABILITIES => new stdClass(),
        ],
    ],
], JSON_THROW_ON_ERROR);

$result = $protocol->handle($payload, [
    'MCP-Protocol-Version' => ProtocolVersion::V2026_07_28->value,
    'Mcp-Method' => $method,
]);
$body = json_decode($result->toJson(), true, 512, JSON_THROW_ON_ERROR);

if (200 !== $result->httpStatus || ['2026-07-28'] !== ($body['result']['supportedVersions'] ?? null)) {
    fwrite(STDERR, "Stateless MCP discovery smoke test failed.\n");
    exit(1);
}

fwrite(STDOUT, "Stateless MCP discovery passed for protocol 2026-07-28.\n");
