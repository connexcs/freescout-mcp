<?php

namespace Modules\McpServer\Entities;

use Illuminate\Database\Eloquent\Model;

final class McpOAuthCode extends Model
{
    protected $table = 'mcpserver_oauth_codes';
    protected $guarded = ['id'];
    protected $hidden = ['secret_hash'];
    protected $dates = ['expires_at', 'consumed_at'];

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function client()
    {
        return $this->belongsTo(McpOAuthClient::class, 'client_record_id');
    }
}
