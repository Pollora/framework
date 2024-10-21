<?php

declare(strict_types=1);

namespace Pollora\Theme\Contracts;

interface ThemeComponent
{
    public function register(): void;
}
