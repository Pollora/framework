<?php

declare(strict_types=1);

namespace Pollen\Query\Traits;

trait Password
{
    protected $hasPassword;

    protected $postPassword;

    public function hasPassword(bool $hasPassword): self
    {
        $this->hasPassword = $hasPassword;

        return $this;
    }

    public function postPassword(string $postPassword): self
    {
        $this->postPassword = $postPassword;

        return $this;
    }
}
