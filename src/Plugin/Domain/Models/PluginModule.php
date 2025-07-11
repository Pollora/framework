<?php

declare(strict_types=1);

namespace Pollora\Plugin\Domain\Models;

use Pollora\Modules\Domain\Models\AbstractModule;
use Pollora\Plugin\Domain\Contracts\PluginModuleInterface;

/**
 * Base plugin module implementation.
 *
 * Provides core functionality for WordPress plugins including metadata
 * management, activation/deactivation hooks, and plugin-specific configuration.
 */
class PluginModule extends AbstractModule implements PluginModuleInterface
{
    /**
     * Plugin headers from the main plugin file.
     */
    protected array $pluginHeaders = [];

    /**
     * Plugin activation status.
     */
    protected bool $active = false;

    /**
     * Boot the plugin module.
     */
    public function boot(): void
    {
        // Plugin-specific boot logic will be handled by infrastructure layer
    }

    /**
     * Register the plugin module.
     */
    public function register(): void
    {
        // Plugin-specific registration logic will be handled by infrastructure layer
    }

    /**
     * Check if plugin is enabled.
     *
     * @return bool True if plugin is enabled
     */
    public function isEnabled(): bool
    {
        return $this->active;
    }

    /**
     * Check if plugin is disabled.
     *
     * @return bool True if plugin is disabled
     */
    public function isDisabled(): bool
    {
        return ! $this->active;
    }

    /**
     * Enable the plugin.
     */
    public function enable(): void
    {
        $this->active = true;
    }

    /**
     * Disable the plugin.
     */
    public function disable(): void
    {
        $this->active = false;
    }

    /**
     * Get WordPress plugin data.
     *
     * @return array Plugin data array containing standard WordPress plugin headers
     */
    public function getPluginData(): array
    {
        return [
            'Name' => $this->get('Name', $this->getName()),
            'Description' => $this->getDescription(),
            'Version' => $this->getVersion(),
            'Author' => $this->getAuthor(),
            'PluginURI' => $this->getPluginUri(),
            'AuthorURI' => $this->getAuthorUri(),
            'TextDomain' => $this->getTextDomain(),
            'DomainPath' => $this->getDomainPath(),
            'Network' => $this->isNetworkWide(),
            'RequiresWP' => $this->getRequiredWordPressVersion(),
            'TestedUpTo' => $this->getTestedWordPressVersion(),
            'RequiresPHP' => $this->getRequiredPhpVersion(),
        ];
    }

    /**
     * Get plugin description.
     *
     * @return string Plugin description
     */
    public function getDescription(): string
    {
        return $this->get('Description', '');
    }

    /**
     * Get plugin main file path.
     *
     * @return string Path to the main plugin file
     */
    public function getMainFile(): string
    {
        return $this->getPath().'/'.$this->getLowerName().'.php';
    }

    /**
     * Get plugin version.
     *
     * @return string Plugin version
     */
    public function getVersion(): string
    {
        return $this->get('Version', '1.0.0');
    }

    /**
     * Get plugin author.
     *
     * @return string Plugin author
     */
    public function getAuthor(): string
    {
        return $this->get('Author', '');
    }

    /**
     * Get plugin URI.
     *
     * @return string|null Plugin URI if available
     */
    public function getPluginUri(): ?string
    {
        return $this->get('PluginURI');
    }

    /**
     * Get author URI.
     *
     * @return string|null Author URI if available
     */
    public function getAuthorUri(): ?string
    {
        return $this->get('AuthorURI');
    }

    /**
     * Get plugin network status.
     *
     * @return bool True if plugin is network-wide, false otherwise
     */
    public function isNetworkWide(): bool
    {
        return (bool) $this->get('Network', false);
    }

    /**
     * Get plugin text domain.
     *
     * @return string Plugin text domain for translations
     */
    public function getTextDomain(): string
    {
        return $this->get('TextDomain', $this->getLowerName());
    }

    /**
     * Get plugin domain path.
     *
     * @return string|null Domain path for translations
     */
    public function getDomainPath(): ?string
    {
        return $this->get('DomainPath', '/languages');
    }

    /**
     * Get plugin headers.
     *
     * @return array Plugin headers array
     */
    public function getHeaders(): array
    {
        return $this->pluginHeaders;
    }

    /**
     * Get required WordPress version.
     *
     * @return string|null Required WordPress version if specified
     */
    public function getRequiredWordPressVersion(): ?string
    {
        return $this->get('RequiresWP');
    }

    /**
     * Get tested WordPress version.
     *
     * @return string|null Tested WordPress version if specified
     */
    public function getTestedWordPressVersion(): ?string
    {
        return $this->get('TestedUpTo');
    }

    /**
     * Get required PHP version.
     *
     * @return string|null Required PHP version if specified
     */
    public function getRequiredPhpVersion(): ?string
    {
        return $this->get('RequiresPHP');
    }

    /**
     * Check if plugin is active.
     *
     * @return bool True if plugin is active, false otherwise
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Activate the plugin.
     */
    public function activate(): void
    {
        $this->active = true;
    }

    /**
     * Deactivate the plugin.
     */
    public function deactivate(): void
    {
        $this->active = false;
    }

    /**
     * Get plugin slug.
     *
     * @return string Plugin slug used in WordPress
     */
    public function getSlug(): string
    {
        return $this->getLowerName();
    }

    /**
     * Get plugin basename.
     *
     * @return string Plugin basename (directory/main-file.php)
     */
    public function getBasename(): string
    {
        return $this->getLowerName().'/'.$this->getLowerName().'.php';
    }

    /**
     * Set plugin headers.
     *
     * @param  array  $headers  Plugin headers array
     */
    public function setHeaders(array $headers): static
    {
        $this->pluginHeaders = $headers;

        // Map headers to metadata
        foreach ($headers as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * Set plugin active status.
     *
     * @param  bool  $active  Active status
     */
    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Set plugin enabled status.
     *
     * @param  bool  $enabled  Enabled status
     */
    public function setEnabled(bool $enabled): static
    {
        if ($enabled) {
            $this->enable();
        } else {
            $this->disable();
        }

        return $this;
    }

    /**
     * Get root namespace for plugins.
     *
     * @return string Root namespace
     */
    public function getRootNamespace(): string
    {
        return 'Plugin';
    }

    /**
     * Get plugin namespace.
     *
     * @return string Plugin namespace
     */
    public function getNamespace(): string
    {
        return $this->getRootNamespace().'\\'.$this->normalizePluginName($this->getLowerName());
    }

    /**
     * Normalize plugin name to be PSR-4 compliant.
     *
     * @param  string  $pluginName  Plugin name
     * @return string Normalized plugin name
     */
    protected function normalizePluginName(string $pluginName): string
    {
        return str_replace(['-', '_', ' '], '', ucwords($pluginName, '-_ '));
    }
}
