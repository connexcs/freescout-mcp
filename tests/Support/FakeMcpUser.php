<?php

namespace Modules\McpServer\Tests\Support;

final class FakeMcpUser
{
    public $type;
    public $id;
    private $active;
    private $admin;
    private $deleted;

    public function __construct(bool $active, bool $admin, int $type = 1, int $id = 1, bool $deleted = false)
    {
        $this->active = $active;
        $this->admin = $admin;
        $this->type = $type;
        $this->id = $id;
        $this->deleted = $deleted;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }
}
