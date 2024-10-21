<?php

declare(strict_types=1);

namespace Pollora\Gutenberg;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Gutenberg\Registrars\CategoryRegistrar;
use Pollora\Gutenberg\Registrars\PatternRegistrar;
use Pollora\Support\Facades\Action;
use Pollora\Theme\Contracts\ThemeComponent;

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
        Action::add('init', function (): void {
            if (wp_installing()) {
                return;
            }
            $this->categoryRegistrar->register();
            $this->patternRegistrar->register();
        });
    }
}
