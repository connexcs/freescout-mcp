<?php

namespace Modules\McpServer\Security;

use Modules\McpServer\Entities\McpToken;

final class IssuedToken
{
    /** @var McpToken */
    public $record;

    /** @var string */
    public $plainText;

    public function __construct(McpToken $record, string $plainText)
    {
        $this->record = $record;
        $this->plainText = $plainText;
    }
}
