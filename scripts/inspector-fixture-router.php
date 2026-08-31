<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Mcp\Server\Transport\StatelessHttpTransport;
use Modules\McpServer\Services\McpServerFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\NullLogger;

if ('/health' === parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)) {
    http_response_code(204);
    return;
}

$expected = getenv('MCP_INSPECTOR_TEST_TOKEN') ?: 'inspector-test-token';
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!hash_equals('Bearer '.$expected, $authorization)) {
    http_response_code(401);
    header('Content-Type: application/json');
    header('WWW-Authenticate: Bearer realm="FreeScout MCP", error="invalid_token"');
    echo json_encode(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32001, 'message' => 'Unauthorized']], JSON_THROW_ON_ERROR);
    return;
}

$factory = new Psr17Factory();
$uri = 'http://'.($_SERVER['HTTP_HOST'] ?? '127.0.0.1').($_SERVER['REQUEST_URI'] ?? '/mcp');
$request = $factory->createServerRequest($_SERVER['REQUEST_METHOD'] ?? 'POST', $uri, $_SERVER);
foreach (getallheaders() ?: [] as $name => $value) {
    $request = $request->withHeader($name, $value);
}
$request = $request->withBody($factory->createStream((string) file_get_contents('php://input')));

$transport = new StatelessHttpTransport(
    (new McpServerFactory())->build(),
    $factory,
    $factory,
    new NullLogger(),
    1024 * 1024,
    []
);
$response = $transport->handle($request);
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header($name.': '.$value, false);
    }
}
echo (string) $response->getBody();

