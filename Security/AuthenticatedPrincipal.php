<?php

namespace Modules\McpServer\Security;

final class AuthenticatedPrincipal
{
    /** @var object */
    public $user;

    /** @var object */
    public $token;

    /** @param object $user @param object $token */
    public function __construct($user, $token)
    {
        $this->user = $user;
        $this->token = $token;
    }
}
