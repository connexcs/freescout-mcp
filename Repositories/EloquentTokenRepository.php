<?php

namespace Modules\McpServer\Repositories;

use Carbon\Carbon;
use Modules\McpServer\Contracts\TokenRepository;
use Modules\McpServer\Entities\McpToken;

final class EloquentTokenRepository implements TokenRepository
{
    public function findBySelector(string $selector)
    {
        return McpToken::with('user')->where('selector', $selector)->first();
    }

    public function markUsed($token, ?string $ipAddress): void
    {
        if (null !== $token->last_used_at && $token->last_used_at->gt(Carbon::now()->subMinutes(5))) {
            return;
        }

        $token->last_used_at = Carbon::now();
        $token->last_used_ip = null === $ipAddress ? null : substr($ipAddress, 0, 45);
        $token->save();
    }
}
