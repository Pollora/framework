<?php

declare(strict_types=1);

namespace Pollora\Gutenberg\Application\Services;

use Pollora\Gutenberg\Registrars\BlockCategoryRegistrar;
use Pollora\Gutenberg\Registrars\PatternCategoryRegistrar;
use Pollora\Gutenberg\Registrars\PatternRegistrar;

/**
 * Application service for orchestrating the registration of Gutenberg patterns and categories.
 */
class PatternRegistrationService
{
    protected PatternCategoryRegistrar $patternCategoryRegistrar;

    protected BlockCategoryRegistrar $blockCategoryRegistrar;

    protected PatternRegistrar $patternRegistrar;

    /**
     * PatternRegistrationService constructor.
     */
    public function __construct(
        PatternCategoryRegistrar $patternCategoryRegistrar,
        BlockCategoryRegistrar $blockCategoryRegistrar,
        PatternRegistrar $patternRegistrar
    ) {
        $this->patternCategoryRegistrar = $patternCategoryRegistrar;
        $this->blockCategoryRegistrar = $blockCategoryRegistrar;
        $this->patternRegistrar = $patternRegistrar;
    }

    /**
     * Register all Gutenberg patterns and categories.
     */
    public function registerAll(): void
    {
        $this->patternCategoryRegistrar->register();
        $this->blockCategoryRegistrar->register();
        $this->patternRegistrar->register();
    }
}
