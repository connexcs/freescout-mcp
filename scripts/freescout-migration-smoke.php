<?php

if (!isset($argv[1])) {
    fwrite(STDERR, "Usage: APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php scripts/freescout-migration-smoke.php /path/to/freescout\n");
    exit(2);
}

$root = realpath($argv[1]);
if (false === $root || !is_file($root.'/bootstrap/app.php')) {
    fwrite(STDERR, "The supplied directory is not a FreeScout checkout.\n");
    exit(2);
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (!$app->environment('testing') || 'sqlite' !== config('database.default') || ':memory:' !== config('database.connections.sqlite.database')) {
    fwrite(STDERR, "Refusing to run unless an in-memory SQLite testing database is configured.\n");
    exit(2);
}

\Schema::create('users', function ($table) {
    $table->increments('id');
});

require dirname(__DIR__).'/Database/Migrations/2026_08_30_000000_create_mcpserver_tokens_table.php';
require dirname(__DIR__).'/Database/Migrations/2026_08_31_000000_create_mcpserver_mutation_tables.php';

$tokens = new \CreateMcpserverTokensTable();
$mutations = new \CreateMcpserverMutationTables();
$tokens->up();
$mutations->up();

foreach (['mcpserver_tokens', 'mcpserver_audit_logs', 'mcpserver_idempotency'] as $table) {
    if (!\Schema::hasTable($table)) {
        throw new \RuntimeException('Migration did not create '.$table.'.');
    }
}
foreach (['tool', 'outcome', 'argument_meta', 'error_code'] as $column) {
    if (!\Schema::hasColumn('mcpserver_audit_logs', $column)) {
        throw new \RuntimeException('Audit migration is missing '.$column.'.');
    }
}

$mutations->down();
$tokens->down();
if (\Schema::hasTable('mcpserver_audit_logs') || \Schema::hasTable('mcpserver_idempotency') || \Schema::hasTable('mcpserver_tokens')) {
    throw new \RuntimeException('Migration rollback did not remove module tables.');
}

fwrite(STDOUT, "FreeScout mutation and token migrations passed on SQLite.\n");
