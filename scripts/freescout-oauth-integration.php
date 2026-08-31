<?php

if (!isset($argv[1])) {
    fwrite(STDERR, "Usage: APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php scripts/freescout-oauth-integration.php /path/to/freescout\n");
    exit(2);
}

$root = realpath($argv[1]);
if (false === $root || !is_file($root.'/bootstrap/app.php')) {
    fwrite(STDERR, "The supplied directory is not a FreeScout checkout.\n");
    exit(2);
}

require $root.'/vendor/autoload.php';
require dirname(__DIR__).'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

if (!$app->environment('testing') || 'sqlite' !== config('database.default') || ':memory:' !== config('database.connections.sqlite.database')) {
    fwrite(STDERR, "Refusing to run unless an in-memory SQLite testing database is configured.\n");
    exit(2);
}

\Schema::create('users', function ($table) {
    $table->increments('id');
    $table->string('email');
    $table->integer('role')->default(1);
    $table->integer('type')->default(1);
    $table->integer('status')->default(1);
    $table->string('timezone')->default('UTC');
    $table->timestamps();
});
require dirname(__DIR__).'/Database/Migrations/2026_09_01_000000_create_mcpserver_oauth_tables.php';
(new \CreateMcpserverOauthTables())->up();

$userId = \DB::table('users')->insertGetId([
    'email' => 'oauth-integration@example.test',
    'role' => \App\User::ROLE_USER,
    'type' => \App\User::TYPE_USER,
    'status' => \App\User::STATUS_ACTIVE,
    'timezone' => 'UTC',
]);
$user = \App\User::findOrFail($userId);

$client = \Modules\McpServer\Entities\McpOAuthClient::create([
    'client_id' => 'fsmcp_client_integration',
    'client_id_hash' => hash('sha256', 'fsmcp_client_integration'),
    'client_name' => 'Integration Client',
    'redirect_uris' => ['https://client.example/callback'],
    'application_type' => 'web',
    'source' => 'dcr',
    'metadata_json' => ['client_id' => 'fsmcp_client_integration', 'client_name' => 'Integration Client', 'redirect_uris' => ['https://client.example/callback']],
]);

$codec = new \Modules\McpServer\OAuth\OAuthCredentialCodec(new \Modules\McpServer\Security\TokenCodec('integration-pepper'));
$policy = new \Modules\McpServer\Security\TokenPolicy(static fn ($name, $default) => $default);
$service = new \Modules\McpServer\OAuth\OAuthTokenService($codec, $policy);
$resource = 'https://freescout.example/mcp';
$verifier = str_repeat('v', 64);
$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

$code = $service->issueCode($user, $client, 'https://client.example/callback', $resource, ['mcp:read', 'mcp:write'], $challenge);
$storedCode = \Modules\McpServer\Entities\McpOAuthCode::firstOrFail();
if (str_contains($storedCode->secret_hash, $code)) {
    throw new \RuntimeException('Authorization code plaintext was stored.');
}

$pair = $service->exchangeCode($code, $client->client_id, 'https://client.example/callback', $resource, $verifier);
if ('Bearer' !== $pair['token_type'] || false === strpos($pair['scope'], 'mcp:write')) {
    throw new \RuntimeException('Authorization code exchange did not issue the expected scoped pair.');
}
$principal = $service->authenticateAccess($pair['access_token'], '192.0.2.5', $resource);
if (null === $principal || (int) $principal->user->id !== (int) $user->id || !$principal->hasScope('mcp:write')) {
    throw new \RuntimeException('Audience-bound OAuth access authentication failed.');
}
if (null !== $service->authenticateAccess($pair['access_token'], null, 'https://other.example/mcp')) {
    throw new \RuntimeException('Access token was accepted for the wrong resource.');
}

try {
    $service->exchangeCode($code, $client->client_id, 'https://client.example/callback', $resource, $verifier);
    throw new \RuntimeException('Authorization code replay was accepted.');
} catch (\Modules\McpServer\OAuth\OAuthException $exception) {
    if ('invalid_grant' !== $exception->error) {
        throw $exception;
    }
}

$rotated = $service->refresh($pair['refresh_token'], $client->client_id, $resource, 'mcp:read');
if (null === $service->authenticateAccess($rotated['access_token'], null, $resource)) {
    throw new \RuntimeException('Rotated access token was not accepted.');
}
try {
    $service->refresh($pair['refresh_token'], $client->client_id, $resource);
    throw new \RuntimeException('Rotated refresh token reuse was accepted.');
} catch (\Modules\McpServer\OAuth\OAuthException $exception) {
    if ('invalid_grant' !== $exception->error) {
        throw $exception;
    }
}
if (null !== $service->authenticateAccess($rotated['access_token'], null, $resource)) {
    throw new \RuntimeException('Refresh reuse did not revoke the token family.');
}

$code2 = $service->issueCode($user, $client, 'https://client.example/callback', $resource, ['mcp:read'], $challenge);
$pair2 = $service->exchangeCode($code2, $client->client_id, 'https://client.example/callback', $resource, $verifier);
try {
    $service->refresh($pair2['refresh_token'], $client->client_id, $resource, 'mcp:write');
    throw new \RuntimeException('Refresh-token scope escalation was accepted.');
} catch (\Modules\McpServer\OAuth\OAuthException $exception) {
    if ('invalid_scope' !== $exception->error) {
        throw $exception;
    }
}
$service->revoke($pair2['refresh_token']);
if (null !== $service->authenticateAccess($pair2['access_token'], null, $resource)) {
    throw new \RuntimeException('Refresh-token revocation did not revoke related access tokens.');
}

fwrite(STDOUT, "FreeScout OAuth code, PKCE, audience, scope enforcement, rotation, reuse detection, and revocation passed.\n");
