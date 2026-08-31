<?php

namespace Modules\McpServer\Entities;

use Illuminate\Database\Eloquent\Model;

final class McpAuditLog extends Model
{
    public $timestamps = false;
    protected $table = 'mcpserver_audit_logs';
    protected $guarded = ['id'];
    protected $casts = ['argument_meta' => 'array'];
}
