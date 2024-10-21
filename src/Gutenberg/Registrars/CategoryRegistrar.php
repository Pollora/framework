<?php

declare(strict_types=1);

namespace Pollora\Gutenberg\Registrars;

class CategoryRegistrar
{
    public function register(): void
    {
        collect(config('theme.gutenberg.categories.patterns'))
            ->each(fn ($args, $key) => register_block_pattern_category($key, $args));
    }
}
