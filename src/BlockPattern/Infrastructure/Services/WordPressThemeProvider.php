<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Infrastructure\Services;

use Pollora\BlockPattern\Domain\Contracts\ThemeProviderInterface;

/**
 * WordPress implementation of ThemeProviderInterface.
 *
 * This is an adapter in hexagonal architecture that connects
 * our domain to WordPress for theme information.
 */
class WordPressThemeProvider implements ThemeProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getActiveThemes(): array
    {
        if (!function_exists('get_stylesheet') || !function_exists('get_template') || !function_exists('wp_get_theme')) {
            return [];
        }
        
        $stylesheet = \get_stylesheet();
        $template = \get_template();

        return $stylesheet === $template
            ? [\wp_get_theme($stylesheet)]
            : [\wp_get_theme($stylesheet), \wp_get_theme($template)];
    }
} 