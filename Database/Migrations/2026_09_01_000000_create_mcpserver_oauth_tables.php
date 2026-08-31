<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMcpserverOauthTables extends Migration
{
    public function up()
    {
        Schema::create('mcpserver_oauth_clients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('client_id');
            $table->char('client_id_hash', 64)->unique();
            $table->string('client_name', 150);
            $table->text('redirect_uris');
            $table->string('application_type', 10)->default('web');
            $table->string('source', 10);
            $table->longText('metadata_json')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mcpserver_oauth_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('client_record_id');
            $table->string('selector', 32)->unique();
            $table->char('secret_hash', 64);
            $table->text('redirect_uri');
            $table->text('resource');
            $table->string('scopes', 255);
            $table->string('code_challenge', 128);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('client_record_id')->references('id')->on('mcpserver_oauth_clients')->onDelete('cascade');
        });

        Schema::create('mcpserver_oauth_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('client_record_id');
            $table->string('type', 10);
            $table->string('selector', 32)->unique();
            $table->char('secret_hash', 64);
            $table->string('family_id', 32);
            $table->text('resource');
            $table->string('scopes', 255);
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['family_id', 'type']);
            $table->index(['user_id', 'revoked_at']);
            $table->index('expires_at');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('client_record_id')->references('id')->on('mcpserver_oauth_clients')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mcpserver_oauth_tokens');
        Schema::dropIfExists('mcpserver_oauth_codes');
        Schema::dropIfExists('mcpserver_oauth_clients');
    }
}
