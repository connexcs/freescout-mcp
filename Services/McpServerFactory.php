<?php

namespace Modules\McpServer\Services;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\ServerCapabilities;
use Mcp\Server;
use Mcp\Server\Stateless\StatelessProtocol;
use Mcp\Server\Wire\CachePolicy;
use Modules\McpServer\Support\LegacyCompatibleContainer;
use Modules\McpServer\Tools\ReadToolCatalogue;
use Modules\McpServer\Mutations\MutationToolCatalogue;

final class McpServerFactory
{
    /** @var array<string, mixed> */
    private $config;
    private $catalogue;
    private $mutations;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [], ?ReadToolCatalogue $catalogue = null, ?MutationToolCatalogue $mutations = null)
    {
        $this->config = $config;
        $this->catalogue = $catalogue;
        $this->mutations = $mutations;
    }

    public function build(): StatelessProtocol
    {
        $ttl = max(0, (int) ($this->config['catalog_ttl_ms'] ?? 300000));

        $builder = Server::builder()
            ->setServerInfo(
                (string) ($this->config['server_name'] ?? 'freescout-mcp'),
                (string) ($this->config['server_version'] ?? '0.4.0'),
                'Permission-aware FreeScout capabilities over MCP.',
                null,
                null,
                (string) ($this->config['server_title'] ?? 'FreeScout MCP Server')
            )
            ->setCapabilities(new ServerCapabilities(
                tools: true,
                resources: false,
                prompts: false
            ))
            ->setCachePolicy(
                CachePolicy::none()
                    ->withMethod('server/discover', $ttl)
                    ->withMethod('tools/list', $ttl)
            )
            ->setContainer(new LegacyCompatibleContainer())
            ->setHeaderValidator(true);

        if (null !== $this->catalogue) {
            $this->catalogue->register($builder);
        }
        if (null !== $this->mutations && $this->mutations->enabled()) {
            $this->mutations->register($builder);
        }

        return $builder->buildStateless([ProtocolVersion::V2026_07_28]);
    }
}
