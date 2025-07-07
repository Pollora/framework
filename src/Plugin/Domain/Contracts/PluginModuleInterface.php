<?php

declare(strict_types=1);

namespace Pollora\Plugin\Domain\Contracts;

use Pollora\Modules\Domain\Contracts\ModuleInterface;

/**
 * Interface for plugin modules in the Pollora framework.
 *
 * Extends the base ModuleInterface to provide plugin-specific functionality
 * including WordPress plugin metadata, activation/deactivation hooks, and
 * plugin-specific configuration.
 */
interface PluginModuleInterface extends ModuleInterface
{
    /**
     * Get WordPress plugin data.
     *
     * @return array Plugin data array containing standard WordPress plugin headers
     */
    public function getPluginData(): array;

    /**
     * Get plugin main file path.
     *
     * @return string Path to the main plugin file
     */
    public function getMainFile(): string;

    /**
     * Get plugin version.
     *
     * @return string Plugin version
     */
    public function getVersion(): string;

    /**
     * Get plugin author.
     *
     * @return string Plugin author
     */
    public function getAuthor(): string;

    /**
     * Get plugin URI.
     *
     * @return string|null Plugin URI if available
     */
    public function getPluginUri(): ?string;

    /**
     * Get author URI.
     *
     * @return string|null Author URI if available
     */
    public function getAuthorUri(): ?string;

    /**
     * Get plugin network status.
     *
     * @return bool True if plugin is network-wide, false otherwise
     */
    public function isNetworkWide(): bool;

    /**
     * Get plugin text domain.
     *
     * @return string Plugin text domain for translations
     */
    public function getTextDomain(): string;

    /**
     * Get plugin domain path.
     *
     * @return string|null Domain path for translations
     */
    public function getDomainPath(): ?string;

    /**
     * Get plugin headers.
     *
     * @return array Plugin headers array
     */
    public function getHeaders(): array;

    /**
     * Get required WordPress version.
     *
     * @return string|null Required WordPress version if specified
     */
    public function getRequiredWordPressVersion(): ?string;

    /**
     * Get tested WordPress version.
     *
     * @return string|null Tested WordPress version if specified
     */
    public function getTestedWordPressVersion(): ?string;

    /**
     * Get required PHP version.
     *
     * @return string|null Required PHP version if specified
     */
    public function getRequiredPhpVersion(): ?string;

    /**
     * Check if plugin is active.
     *
     * @return bool True if plugin is active, false otherwise
     */
    public function isActive(): bool;

    /**
     * Activate the plugin.
     *
     * @return void
     */
    public function activate(): void;

    /**
     * Deactivate the plugin.
     *
     * @return void
     */
    public function deactivate(): void;

    /**
     * Get plugin slug.
     *
     * @return string Plugin slug used in WordPress
     */
    public function getSlug(): string;

    /**
     * Get plugin basename.
     *
     * @return string Plugin basename (directory/main-file.php)
     */
    public function getBasename(): string;
}