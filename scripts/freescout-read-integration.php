<?php

if (!isset($argv[1])) {
    fwrite(STDERR, "Usage: APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php scripts/freescout-read-integration.php /path/to/freescout\n");
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

if (!$app->environment('testing') || 'sqlite' !== config('database.default') || ':memory:' !== config('database.connections.sqlite.database')) {
    fwrite(STDERR, "Refusing to run unless an in-memory SQLite testing database is configured.\n");
    exit(2);
}

$schema = \Schema::connection('sqlite');
$schema->create('users', function ($table) {
    $table->increments('id'); $table->string('first_name')->nullable(); $table->string('last_name')->nullable();
    $table->string('email'); $table->integer('role'); $table->integer('type'); $table->integer('status');
    $table->text('permissions')->nullable(); $table->timestamps();
});
$schema->create('mailboxes', function ($table) {
    $table->increments('id'); $table->string('name'); $table->string('email'); $table->timestamps();
});
$schema->create('mailbox_user', function ($table) {
    $table->increments('id'); $table->integer('mailbox_id'); $table->integer('user_id'); $table->text('access')->nullable();
    $table->boolean('hide')->default(false); $table->boolean('mute')->default(false);
});
$schema->create('customers', function ($table) {
    $table->increments('id'); $table->string('first_name')->nullable(); $table->string('last_name')->nullable();
    $table->string('company')->nullable(); $table->timestamps();
});
$schema->create('emails', function ($table) {
    $table->increments('id'); $table->integer('customer_id'); $table->string('email'); $table->integer('type')->default(1);
});
$schema->create('conversations', function ($table) {
    $table->increments('id'); $table->integer('number'); $table->integer('threads_count')->default(0);
    $table->integer('type')->default(1); $table->integer('status')->default(1); $table->integer('state')->default(2);
    $table->string('subject')->nullable(); $table->string('customer_email')->nullable(); $table->string('preview')->default('');
    $table->boolean('has_attachments')->default(false); $table->integer('mailbox_id'); $table->integer('user_id')->nullable();
    $table->integer('customer_id')->nullable(); $table->integer('created_by_user_id')->nullable();
    $table->timestamp('last_reply_at')->nullable(); $table->timestamps();
});
$schema->create('threads', function ($table) {
    $table->increments('id'); $table->integer('conversation_id'); $table->integer('type'); $table->integer('state');
    $table->text('body')->nullable(); $table->string('from')->nullable(); $table->text('to')->nullable(); $table->text('cc')->nullable();
    $table->integer('created_by_user_id')->nullable(); $table->integer('created_by_customer_id')->nullable(); $table->timestamps();
});
$schema->create('attachments', function ($table) {
    $table->increments('id'); $table->integer('thread_id'); $table->boolean('embedded')->default(false);
    $table->string('file_name'); $table->string('mime_type')->nullable(); $table->integer('size')->nullable();
});
$schema->create('modules', function ($table) {
    $table->increments('id'); $table->string('alias')->unique(); $table->boolean('active'); $table->boolean('activated')->default(false); $table->string('license')->nullable();
});
$schema->create('kb_categories', function ($table) {
    $table->increments('id'); $table->integer('mailbox_id'); $table->string('name'); $table->integer('parent_id')->nullable();
});
$schema->create('kb_articles', function ($table) {
    $table->increments('id'); $table->integer('mailbox_id'); $table->integer('category_id')->nullable(); $table->string('title'); $table->text('body');
});

$now = \Carbon\Carbon::now();
$userA = \DB::table('users')->insertGetId(['first_name' => 'Alice', 'last_name' => 'Agent', 'email' => 'alice@example.test', 'role' => \App\User::ROLE_USER, 'type' => \App\User::TYPE_USER, 'status' => \App\User::STATUS_ACTIVE, 'permissions' => null, 'created_at' => $now, 'updated_at' => $now]);
$userB = \DB::table('users')->insertGetId(['first_name' => 'Bob', 'last_name' => 'Agent', 'email' => 'bob@example.test', 'role' => \App\User::ROLE_USER, 'type' => \App\User::TYPE_USER, 'status' => \App\User::STATUS_ACTIVE, 'permissions' => null, 'created_at' => $now, 'updated_at' => $now]);
$limited = \DB::table('users')->insertGetId(['first_name' => 'Limited', 'last_name' => 'Agent', 'email' => 'limited@example.test', 'role' => \App\User::ROLE_USER, 'type' => \App\User::TYPE_USER, 'status' => \App\User::STATUS_ACTIVE, 'permissions' => json_encode([\App\User::PERM_ONLY_ASSIGNED_TICKETS => 1]), 'created_at' => $now, 'updated_at' => $now]);
$mailboxA = \DB::table('mailboxes')->insertGetId(['name' => 'A', 'email' => 'a@example.test', 'created_at' => $now, 'updated_at' => $now]);
$mailboxB = \DB::table('mailboxes')->insertGetId(['name' => 'B', 'email' => 'b@example.test', 'created_at' => $now, 'updated_at' => $now]);
foreach ([[$mailboxA, $userA], [$mailboxB, $userB], [$mailboxA, $limited]] as $access) {
    \DB::table('mailbox_user')->insert(['mailbox_id' => $access[0], 'user_id' => $access[1], 'access' => null]);
}
$customerA = \DB::table('customers')->insertGetId(['first_name' => 'Allowed', 'last_name' => 'Customer', 'company' => null, 'created_at' => $now, 'updated_at' => $now]);
$customerB = \DB::table('customers')->insertGetId(['first_name' => 'Secret', 'last_name' => 'Customer', 'company' => null, 'created_at' => $now, 'updated_at' => $now]);
\DB::table('emails')->insert([['customer_id' => $customerA, 'email' => 'allowed@example.test'], ['customer_id' => $customerB, 'email' => 'secret@example.test']]);
$ticketA = \DB::table('conversations')->insertGetId(['number' => 101, 'subject' => 'Allowed ticket', 'customer_email' => 'allowed@example.test', 'preview' => 'visible', 'mailbox_id' => $mailboxA, 'user_id' => $userA, 'customer_id' => $customerA, 'created_by_user_id' => $userA, 'last_reply_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
$ticketB = \DB::table('conversations')->insertGetId(['number' => 202, 'subject' => 'Secret ticket', 'customer_email' => 'secret@example.test', 'preview' => 'classified', 'mailbox_id' => $mailboxB, 'user_id' => $userB, 'customer_id' => $customerB, 'created_by_user_id' => $userB, 'last_reply_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
$ticketLimited = \DB::table('conversations')->insertGetId(['number' => 303, 'subject' => 'Assigned ticket', 'customer_email' => 'allowed@example.test', 'preview' => 'assigned', 'mailbox_id' => $mailboxA, 'user_id' => $limited, 'customer_id' => $customerA, 'created_by_user_id' => $userA, 'last_reply_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
\DB::table('modules')->insert(['alias' => 'knowledgebase', 'active' => true]);
$categoryA = \DB::table('kb_categories')->insertGetId(['mailbox_id' => $mailboxA, 'name' => 'Allowed category']);
$categoryB = \DB::table('kb_categories')->insertGetId(['mailbox_id' => $mailboxB, 'name' => 'Secret category']);
$articleA = \DB::table('kb_articles')->insertGetId(['mailbox_id' => $mailboxA, 'category_id' => $categoryA, 'title' => 'Allowed article', 'body' => '<p>Visible body</p>']);
$articleB = \DB::table('kb_articles')->insertGetId(['mailbox_id' => $mailboxB, 'category_id' => $categoryB, 'title' => 'Secret article', 'body' => '<p>Classified body</p>']);

$context = new \Modules\McpServer\Security\McpRequestContext();
$repository = new \Modules\McpServer\Repositories\FreeScoutReadRepository($context);
$setUser = static function (int $id) use ($context) {
    $user = \App\User::findOrFail($id);
    \Auth::setUser($user);
    $context->set(new \Modules\McpServer\Security\AuthenticatedPrincipal($user, (object) ['id' => 1]));
};

$setUser($userA);
if (null === $repository->ticket($ticketA) || null !== $repository->ticket($ticketB)) {
    throw new \RuntimeException('Direct ticket authorization failed.');
}
$secretSearch = $repository->searchTickets('Secret', [], 25, null);
if ([] !== $secretSearch['items'] || null !== $secretSearch['next_cursor']) {
    throw new \RuntimeException('Cross-mailbox search leaked a ticket or cursor.');
}
$mailboxes = $repository->mailboxes(25, null);
if (1 !== count($mailboxes['items']) || $mailboxA !== $mailboxes['items'][0]['id']) {
    throw new \RuntimeException('Mailbox authorization failed.');
}
$customers = $repository->customers('Customer', 25, null);
if (1 !== count($customers['items']) || $customerA !== $customers['items'][0]['id']) {
    throw new \RuntimeException('Customer authorization failed.');
}
$knowledgeBase = new \Modules\McpServer\KnowledgeBase\KnowledgeBaseRepository($context);
if (!$knowledgeBase->available() || null === $knowledgeBase->article($articleA) || null !== $knowledgeBase->article($articleB)) {
    throw new \RuntimeException('Knowledge Base mailbox authorization failed.');
}
$secretArticles = $knowledgeBase->searchArticles('Secret', 25, null);
if ([] !== $secretArticles['items'] || null !== $secretArticles['next_cursor']) {
    throw new \RuntimeException('Knowledge Base search leaked an article or cursor.');
}

$setUser($limited);
if (null !== $repository->ticket($ticketA) || null === $repository->ticket($ticketLimited)) {
    throw new \RuntimeException('Assigned-only ticket authorization failed.');
}

fwrite(STDOUT, "FreeScout cross-mailbox, customer, search, assigned-only, and Knowledge Base authorization passed.\n");
