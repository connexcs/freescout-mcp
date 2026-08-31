<?php

namespace Modules\McpServer\Entities;

use Illuminate\Database\Eloquent\Model;

final class McpIdempotency extends Model
{
    protected $table = 'mcpserver_idempotency';
    protected $guarded = ['id'];
    protected $dates = ['completed_at'];
}
