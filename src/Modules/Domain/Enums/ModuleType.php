<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Enums;

use Pollora\Plugin\Application\Services\PluginRegistrar;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;

/**
 * Supported module types for Pollora framework registration.
 *
 * Used by the {@see \pollora_register()} helper to identify which registrar
 * should handle the module. Each case maps to a specific registration service:
 *
 * - **Theme**: Registered via {@see ThemeRegistrarInterface}.
 *   Auto-detects the active theme name and path from WordPress.
 *
 * - **Plugin**: Registered via {@see PluginRegistrar}.
 *   Requires an explicit plugin name and directory path.
 *
 * @example
 * ```php
 * use Pollora\Modules\Domain\Enums\ModuleType;
 *
 * // In a theme's functions.php
 * pollora_register(ModuleType::Theme);
 *
 * // In a plugin's main file
 * pollora_register(ModuleType::Plugin, 'my-plugin', __DIR__);
 * ```
 */
enum ModuleType: string
{
    /** WordPress theme — auto-detected from the active stylesheet. */
    case Theme = 'theme';

    /** WordPress plugin — requires explicit name and path. */
    case Plugin = 'plugin';
}
