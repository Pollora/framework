<?php

declare(strict_types=1);

namespace Pollen\Gutenberg;

use Illuminate\Contracts\Foundation\Application;
use Pollen\Gutenberg\Registrars\CategoryRegistrar;
use Pollen\Gutenberg\Registrars\PatternRegistrar;
use Pollen\Support\Facades\Action;
use Pollen\Theme\Contracts\ThemeComponent;

class Pattern implements ThemeComponent
{
    protected CategoryRegistrar $categoryRegistrar;

    protected PatternRegistrar $patternRegistrar;

    public function __construct(Application $container)
    {
        $this->categoryRegistrar = $container->make(CategoryRegistrar::class);
        $this->patternRegistrar = $container->make(PatternRegistrar::class);
    }

    public function register(): void
    {
        Action::add('init', function () {
            if (wp_installing()) {
                return;
            }
            $this->categoryRegistrar->register();
            $this->patternRegistrar->register();
        });
    }
}
