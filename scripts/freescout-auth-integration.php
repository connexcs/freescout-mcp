<?php

if (!isset($argv[1])) {
    fwrite(STDERR, "Usage: APP_ENV=testing php scripts/freescout-auth-integration.php /path/to/freescout\n");
    exit(2);
}

$freeScoutRoot = realpath($argv[1]);
if (false === $freeScoutRoot || !is_file($freeScoutRoot.'/bootstrap/app.php')) {
    fwrite(STDERR, "The supplied directory is not a FreeScout checkout.\n");
    exit(2);
}

require $freeScoutRoot.'/vendor/autoload.php';
$app = require $freeScoutRoot.'/bootstrap/app.php';
$console = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$console->bootstrap();

if (!$app->environment('testing')) {
    fwrite(STDERR, "Refusing to alter a database unless APP_ENV=testing.\n");
    exit(2);
}

if (!\Schema::hasTable('mcpserver_tokens')) {
    fwrite(STDERR, "The MCP token migration has not been run.\n");
    exit(2);
}

// The reduced SQLite fixture does not complete every historical FreeScout
// migration, so its module repository initializes before it can read status.
// Register the provider explicitly after proving the database flag is active.
if (null === $app['router']->getRoutes()->getByName('mcpserver.endpoint') && \App\Module::isActive('mcpserver')) {
    $provider = new \Modules\McpServer\Providers\McpServerServiceProvider($app);
    $provider->register();
    $provider->boot();
    require dirname(__DIR__).'/Http/routes.php';
    $app['router']->getRoutes()->refreshNameLookups();
}

foreach (['mcpserver.endpoint', 'mcpserver.tokens.index', 'mcpserver.tokens.revoke'] as $routeName) {
    if (null === $app['router']->getRoutes()->getByName($routeName)) {
        fwrite(STDERR, sprintf(
            "Module route %s is missing (database active=%s, repository active=%s).\n",
            $routeName,
            \App\Module::isActive('mcpserver') ? 'yes' : 'no',
            \Module::isActive('mcpserver', false) ? 'yes' : 'no'
        ));
        exit(1);
    }
}

$mcpRoute = $app['router']->getRoutes()->getByName('mcpserver.endpoint');
$middleware = $mcpRoute->middleware();
if (!in_array('mcpserver.auth', $middleware, true) || !in_array('mcpserver.throttle', $middleware, true)) {
    fwrite(STDERR, "MCP authentication middleware is not attached to the endpoint.\n");
    exit(1);
}

foreach ([
    dirname(__DIR__).'/Resources/views/settings.blade.php',
    dirname(__DIR__).'/Resources/views/tokens/index.blade.php',
    dirname(__DIR__).'/Resources/views/tokens/menu.blade.php',
] as $viewPath) {
    $app['blade.compiler']->compile($viewPath);
}

$sections = \Eventy::filter('settings.sections', []);
if (!isset($sections['mcpserver'])) {
    fwrite(STDERR, "The MCP administrator settings section is not registered.\n");
    exit(1);
}

$app->make(\Modules\McpServer\Http\Controllers\TokenController::class);

$mcpRouteUri = '/'.ltrim($mcpRoute->uri(), '/');

$connection = \DB::connection();
$connection->beginTransaction();

try {
    config([
        'mcpserver.authenticated_rate_limit' => 2,
        'mcpserver.unauthenticated_rate_limit' => 2,
    ]);

    $userId = \DB::table('users')->insertGetId([
        'first_name' => 'MCP',
        'last_name' => 'Integration',
        'email' => 'mcp-integration-'.bin2hex(random_bytes(5)).'@example.test',
        'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
        'role' => \App\User::ROLE_USER,
        'type' => \App\User::TYPE_USER,
        'status' => \App\User::STATUS_ACTIVE,
        'invite_state' => \App\User::INVITE_STATE_ACTIVATED,
        'timezone' => 'UTC',
        'time_format' => \App\User::TIME_FORMAT_24,
        'created_at' => \Carbon\Carbon::now(),
        'updated_at' => \Carbon\Carbon::now(),
    ]);
    $user = \App\User::findOrFail($userId);

    $issued = $app->make(\Modules\McpServer\Security\TokenIssuer::class)->issue($user, 'Integration test');
    $stored = \Modules\McpServer\Entities\McpToken::findOrFail($issued->record->id);

    $limiter = $app->make(\Illuminate\Cache\RateLimiter::class);
    $codec = $app->make(\Modules\McpServer\Security\TokenCodec::class);
    $limiter->clear('mcpserver:requests:'.$issued->record->id);
    foreach (['192.0.2.10', '192.0.2.20', '192.0.2.30'] as $testIp) {
        $limiter->clear('mcpserver:auth:'.$codec->fingerprint($testIp));
    }

    if ($stored->secret_hash === $issued->plainText || str_contains($stored->secret_hash, $issued->plainText)) {
        throw new \RuntimeException('Plaintext token was stored in the database.');
    }

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'server/discover',
        'params' => [
            '_meta' => [
                \Mcp\Server\Stateless\RequestMeta::PROTOCOL_VERSION => '2026-07-28',
                \Mcp\Server\Stateless\RequestMeta::CLIENT_CAPABILITIES => new \stdClass(),
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $call = static function (string $token, string $ipAddress = '192.0.2.10') use ($app, $payload, $mcpRouteUri) {
        $request = \Illuminate\Http\Request::create(
            $mcpRouteUri,
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_HOST' => 'localhost',
                'HTTP_MCP_METHOD' => 'server/discover',
                'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28',
                'REMOTE_ADDR' => $ipAddress,
            ],
            $payload
        );
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    };

    $valid = $call($issued->plainText);
    if (200 !== $valid->getStatusCode()) {
        throw new \RuntimeException('Valid token request failed: '.$valid->getStatusCode().' '.$valid->getContent());
    }

    if (200 !== $call($issued->plainText)->getStatusCode()
        || 429 !== $call($issued->plainText)->getStatusCode()
    ) {
        throw new \RuntimeException('Authenticated token rate limiting failed.');
    }

    $wrongToken = substr($issued->plainText, 0, -1).('a' === substr($issued->plainText, -1) ? 'b' : 'a');
    $invalid = $call($wrongToken, '192.0.2.20');
    if (401 !== $invalid->getStatusCode() || false === strpos((string) $invalid->headers->get('WWW-Authenticate'), 'invalid_token')) {
        throw new \RuntimeException('Invalid token did not return the expected challenge.');
    }
    if (401 !== $call($wrongToken, '192.0.2.20')->getStatusCode()
        || 429 !== $call($wrongToken, '192.0.2.20')->getStatusCode()
    ) {
        throw new \RuntimeException('Failed-authentication rate limiting failed.');
    }

    $issued->record->revoked_at = \Carbon\Carbon::now();
    $issued->record->save();
    $revoked = $call($issued->plainText, '192.0.2.30');
    if (401 !== $revoked->getStatusCode()) {
        throw new \RuntimeException('Revoked token was accepted.');
    }

    fwrite(STDOUT, "FreeScout module routes, token storage, authentication, and revocation passed.\n");
} finally {
    $connection->rollBack();
}
