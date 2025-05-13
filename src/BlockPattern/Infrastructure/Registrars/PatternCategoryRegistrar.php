<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Infrastructure\Registrars;

use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;

/**
 * Registrar for block pattern categories.
 *
 * Handles the registration of custom block pattern categories
 * defined in the theme configuration.
 */
class PatternCategoryRegistrar
{
    /**
     * Constructor.
     */
    public function __construct(
        private ConfigRepositoryInterface $config
    ) {}

    /**
     * Register configured block pattern categories.
     *
     * Reads categories from theme configuration and registers them
     * with WordPress.
     */
    public function register(): void
    {
        $patterns = $this->config->get('theme.gutenberg.categories.patterns', []);
        
        if (empty($patterns) || !is_array($patterns)) {
            return;
        }
        
        foreach ($patterns as $key => $args) {
            if (function_exists('register_block_pattern_category')) {
                \register_block_pattern_category($key, $args);
            }
        }
    }
} 