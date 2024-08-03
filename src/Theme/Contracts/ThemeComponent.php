<?php

declare(strict_types=1);

namespace Pollen\Theme\Contracts;

interface ThemeComponent
{
    public function register(): void;
}
