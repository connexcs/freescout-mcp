<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMcpserverMutationTables extends Migration
{
    public function up()
    {
        Schema::create('mcpserver_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('tool', 100);
            $table->string('target_type', 40);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('outcome', 30);
            $table->string('idempotency_key', 128)->nullable();
            $table->text('argument_meta')->nullable();
            $table->string('error_code', 60)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['token_id', 'created_at']);
            $table->index(['tool', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('mcpserver_idempotency', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('token_id');
            $table->string('tool', 100);
            $table->string('idempotency_key', 128);
            $table->char('request_hash', 64);
            $table->longText('response_json')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['token_id', 'tool', 'idempotency_key'], 'mcpserver_idempotency_unique');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mcpserver_idempotency');
        Schema::dropIfExists('mcpserver_audit_logs');
    }
}
