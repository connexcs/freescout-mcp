<?php

namespace Modules\McpServer\Tools;

use Mcp\Exception\ToolCallException;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;

final class CallbackToolHandler implements ToolHandlerInterface
{
    /** @var callable */
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function execute(array $arguments, ClientGateway $gateway): mixed
    {
        $result = ($this->callback)($arguments);
        $limit = function_exists('config')
            ? max(1024, (int) config('mcpserver.max_tool_output_bytes', 1024 * 1024))
            : 1024 * 1024;
        try {
            $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ToolCallException('Tool output could not be safely encoded.');
        }
        if (strlen($encoded) > $limit) {
            throw new ToolCallException('Tool output exceeds the configured response limit. Reduce the requested page size.');
        }

        return $result;
    }
}
