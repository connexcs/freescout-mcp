<?php

namespace Modules\McpServer\Entities;

use Illuminate\Database\Eloquent\Model;

final class McpOAuthClient extends Model
{
    protected $table = 'mcpserver_oauth_clients';
    protected $guarded = ['id'];
    protected $casts = ['redirect_uris' => 'array', 'metadata_json' => 'array'];
    protected $dates = ['last_seen_at'];
}
