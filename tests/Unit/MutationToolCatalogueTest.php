<?php

namespace Modules\McpServer\Tests\Unit;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server\Stateless\RequestMeta;
use Modules\McpServer\Mutations\MutationPolicy;
use Modules\McpServer\Mutations\MutationToolCatalogue;
use Modules\McpServer\Mutations\MutationToolService;
use Modules\McpServer\Services\McpServerFactory;
use Modules\McpServer\Tests\Support\FakeMutationRepository;
use Modules\McpServer\Tests\Support\FakeMutationRunner;
use Modules\McpServer\Security\AuthenticatedPrincipal;
use Modules\McpServer\Security\McpRequestContext;
use PHPUnit\Framework\TestCase;

final class MutationToolCatalogueTest extends TestCase
{
    public function testMutationToolsAreExplicitlyAnnotatedAndDraftCannotSend(): void
    {
        $enabled = static fn () => true;
        $catalogue = new MutationToolCatalogue(
            new MutationToolService(new FakeMutationRepository(), new FakeMutationRunner()),
            new MutationPolicy($enabled, $enabled)
        );
        $protocol = (new McpServerFactory([], null, $catalogue))->build();
        $params = ['_meta' => [
            RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value,
            RequestMeta::CLIENT_CAPABILITIES => new \stdClass(),
        ]];
        $result = $protocol->handle(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => $params], JSON_THROW_ON_ERROR), [
            'MCP-Protocol-Version' => ProtocolVersion::V2026_07_28->value,
            'Mcp-Method' => 'tools/list',
        ]);
        $tools = json_decode($result->toJson(), true, 512, JSON_THROW_ON_ERROR)['result']['tools'];

        self::assertCount(3, $tools);
        self::assertSame(['freescout_add_note', 'freescout_update_ticket', 'freescout_create_draft_reply'], array_column($tools, 'name'));
        self::assertFalse($tools[0]['annotations']['readOnlyHint']);
        self::assertTrue($tools[1]['annotations']['destructiveHint']);
        self::assertStringContainsString('never send', $tools[2]['description']);
        self::assertArrayNotHasKey('confirm_send', $tools[2]['inputSchema']['properties']);
    }

    public function testReadOnlyOAuthScopeHidesMutationCatalogue(): void
    {
        $enabled = static fn () => true;
        $context = new McpRequestContext();
        $context->set(new AuthenticatedPrincipal((object) ['id' => 1], (object) ['id' => 2], 'oauth', ['mcp:read']));
        $catalogue = new MutationToolCatalogue(
            new MutationToolService(new FakeMutationRepository(), new FakeMutationRunner()),
            new MutationPolicy($enabled, $enabled),
            $context
        );

        self::assertFalse($catalogue->enabled());
    }
}
