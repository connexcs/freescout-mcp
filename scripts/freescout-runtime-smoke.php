<?php

if (!isset($argv[1])) {
    fwrite(STDERR, "Usage: php scripts/freescout-runtime-smoke.php /path/to/freescout\n");
    exit(2);
}

$freeScoutRoot = realpath($argv[1]);
if (false === $freeScoutRoot || !is_file($freeScoutRoot.'/vendor/autoload.php')) {
    fwrite(STDERR, "The supplied directory is not a Composer-installed FreeScout checkout.\n");
    exit(2);
}

require $freeScoutRoot.'/vendor/autoload.php';

// FreeScout normally registers this facade while bootstrapping Laravel. The
// isolated smoke only needs the protocol decision used by its Request override.
if (!class_exists('Helper', false)) {
    eval('class Helper { public static function isHttps(): bool { return false; } }');
}

// Force the same PSR-11 v1 interface FreeScout loads during application boot.
interface_exists(\Psr\Container\ContainerInterface::class);

require dirname(__DIR__).'/vendor/autoload.php';

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server\Stateless\RequestMeta;
use Mcp\Server\Transport\StatelessHttpTransport;
use Modules\McpServer\Http\LaravelResponseFactory;
use Modules\McpServer\Http\Psr7RequestFactory;
use Modules\McpServer\Services\McpServerFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

$protocol = (new McpServerFactory())->build();
$payload = json_encode([
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'tools/list',
    'params' => [
        '_meta' => [
            RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value,
            RequestMeta::CLIENT_CAPABILITIES => new stdClass(),
        ],
    ],
], JSON_THROW_ON_ERROR);

$result = $protocol->handle($payload, [
    'MCP-Protocol-Version' => ProtocolVersion::V2026_07_28->value,
    'Mcp-Method' => 'tools/list',
]);
$body = json_decode($result->toJson(), true, 512, JSON_THROW_ON_ERROR);

if (200 !== $result->httpStatus || [] !== ($body['result']['tools'] ?? null)) {
    fwrite(STDERR, "FreeScout runtime compatibility smoke test failed.\n");
    exit(1);
}

$request = \Illuminate\Http\Request::create(
    'http://localhost/mcp',
    'POST',
    [],
    [],
    [],
    [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_HOST' => 'localhost',
        'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28',
        'HTTP_MCP_METHOD' => 'tools/list',
    ],
    $payload
);
$factory = new Psr17Factory();
$transport = new StatelessHttpTransport(
    $protocol,
    $factory,
    $factory,
    new \Psr\Log\NullLogger(),
    1024 * 1024,
    []
);
$response = (new LaravelResponseFactory())->create(
    $transport->handle((new Psr7RequestFactory())->create($request))
);
$httpBody = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

if (200 !== $response->getStatusCode() || [] !== ($httpBody['result']['tools'] ?? null)) {
    fwrite(STDERR, "FreeScout Illuminate/PSR HTTP adapter smoke test failed.\n");
    exit(1);
}

fwrite(STDOUT, "FreeScout runtime, HTTP adapters, and stateless MCP SDK compatibility passed.\n");
