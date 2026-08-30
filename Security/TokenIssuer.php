<?php

namespace Modules\McpServer\Security;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Modules\McpServer\Entities\McpToken;

final class TokenIssuer
{
    /** @var TokenCodec */
    private $codec;

    /** @var TokenPolicy */
    private $policy;

    public function __construct(TokenCodec $codec, TokenPolicy $policy)
    {
        $this->codec = $codec;
        $this->policy = $policy;
    }

    /** @param \App\User $user */
    public function issue($user, string $name): IssuedToken
    {
        if (!$this->policy->canIssueForUser($user)) {
            throw new \DomainException('Personal MCP tokens are not permitted for this user.');
        }

        $lastException = null;

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $material = $this->codec->generate();
            $token = new McpToken();
            $token->user_id = $user->id;
            $token->name = trim($name);
            $token->selector = $material['selector'];
            $token->secret_hash = $material['secret_hash'];
            $lifetime = $this->policy->lifetimeDays();
            $token->expires_at = 0 === $lifetime ? null : Carbon::now()->addDays($lifetime);

            try {
                $token->save();

                return new IssuedToken($token, $material['token']);
            } catch (QueryException $exception) {
                if ('23000' !== (string) $exception->getCode()) {
                    throw $exception;
                }
                $lastException = $exception;
            }
        }

        throw $lastException ?: new \RuntimeException('Unable to create MCP token.');
    }
}
