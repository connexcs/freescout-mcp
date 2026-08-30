<?php

namespace Modules\McpServer\Entities;

use Illuminate\Database\Eloquent\Model;

final class McpToken extends Model
{
    protected $table = 'mcpserver_tokens';

    protected $guarded = ['id', 'secret_hash'];

    protected $hidden = ['secret_hash'];

    protected $dates = [
        'expires_at',
        'last_used_at',
        'revoked_at',
        'created_at',
        'updated_at',
    ];

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function getDisplayPrefixAttribute(): string
    {
        return 'fsmcp_'.$this->selector.'_…';
    }

    public function isRevoked(): bool
    {
        return null !== $this->revoked_at;
    }

    public function isExpired(): bool
    {
        return null !== $this->expires_at && $this->expires_at->isPast();
    }
}
