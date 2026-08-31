<?php

namespace Modules\McpServer\Tools;

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
        return ($this->callback)($arguments);
    }
}
