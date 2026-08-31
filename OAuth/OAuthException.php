<?php

namespace Modules\McpServer\OAuth;

final class OAuthException extends \RuntimeException
{
    /** @var string */
    public $error;

    public function __construct(string $error, string $description)
    {
        parent::__construct($description);
        $this->error = $error;
    }
}
