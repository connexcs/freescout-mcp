<?php

namespace Modules\McpServer\Tests\Unit;

use Modules\McpServer\Contracts\TokenRepository;
use Modules\McpServer\Security\TokenAuthenticator;
use Modules\McpServer\Security\TokenCodec;
use Modules\McpServer\Security\TokenPolicy;
use Modules\McpServer\Tests\Support\FakeMcpUser;
use PHPUnit\Framework\TestCase;

final class TokenAuthenticatorTest extends TestCase
{
    public function testValidTokenResolvesItsUserAndRecordsUsage(): void
    {
        [$authenticator, $repository, $material, $record] = $this->fixture();

        $principal = $authenticator->authenticate($material['token'], '192.0.2.4');

        self::assertNotNull($principal);
        self::assertSame($record->user, $principal->user);
        self::assertSame($record, $principal->token);
        self::assertSame([[$record, '192.0.2.4']], $repository->usage);
    }

    public function testWrongSecretRevocationAndExpiryFail(): void
    {
        [$authenticator, $repository, $material, $record] = $this->fixture();
        $parts = explode('_', $material['token'], 3);
        $wrong = $parts[0].'_'.$parts[1].'_'.str_repeat('z', 43);

        self::assertNull($authenticator->authenticate($wrong));

        $record->revoked_at = new \DateTimeImmutable();
        self::assertNull($authenticator->authenticate($material['token']));

        $record->revoked_at = null;
        $record->expires_at = new \DateTimeImmutable('-1 second');
        self::assertNull($authenticator->authenticate($material['token']));

        $record->expires_at = 'not-a-date';
        self::assertNull($authenticator->authenticate($material['token']));
        self::assertSame([], $repository->usage);
    }

    public function testUnknownSelectorAndDisabledUserFail(): void
    {
        [$authenticator, $repository, $material, $record] = $this->fixture();
        $parts = explode('_', $material['token'], 3);
        $unknown = $parts[0].'_'.str_repeat('x', 16).'_'.$parts[2];

        self::assertNull($authenticator->authenticate($unknown));

        $record->user = new FakeMcpUser(false, true);
        self::assertNull($authenticator->authenticate($material['token']));
        self::assertSame([], $repository->usage);
    }

    /** @return array{TokenAuthenticator, FakeTokenRepository, array<string, string>, object} */
    private function fixture(): array
    {
        $codec = new TokenCodec('test-pepper');
        $material = $codec->generate();
        $record = (object) [
            'id' => 1,
            'secret_hash' => $material['secret_hash'],
            'revoked_at' => null,
            'expires_at' => new \DateTimeImmutable('+1 hour'),
            'user' => new FakeMcpUser(true, false),
        ];
        $repository = new FakeTokenRepository($material['selector'], $record);
        $policy = new TokenPolicy(static fn ($name, $default) => $default);

        return [new TokenAuthenticator($repository, $codec, $policy), $repository, $material, $record];
    }
}

final class FakeTokenRepository implements TokenRepository
{
    private $selector;
    private $record;
    public $usage = [];

    public function __construct(string $selector, object $record)
    {
        $this->selector = $selector;
        $this->record = $record;
    }

    public function findBySelector(string $selector)
    {
        return hash_equals($this->selector, $selector) ? $this->record : null;
    }

    public function markUsed($token, ?string $ipAddress): void
    {
        $this->usage[] = [$token, $ipAddress];
    }
}
