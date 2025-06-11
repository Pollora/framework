<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Models;

use Pollora\Modules\Domain\Models\AbstractModule;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;

class ThemeModule extends AbstractModule implements ThemeModuleInterface
{
    protected array $themeHeaders = [];

    protected bool $enabled = false;

    public function boot(): void
    {
        // Theme-specific boot logic will be handled by infrastructure layer
    }

    public function register(): void
    {
        // Theme-specific registration logic will be handled by infrastructure layer
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isDisabled(): bool
    {
        return ! $this->enabled;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function getThemeData(): array
    {
        return [
            'Name' => $this->getName(),
            'Description' => $this->getDescription(),
            'Version' => $this->getVersion(),
            'Author' => $this->getAuthor(),
            'ThemeURI' => $this->getThemeUri(),
            'AuthorURI' => $this->getAuthorUri(),
            'Template' => $this->getParentTheme(),
            'Stylesheet' => $this->getStylesheet(),
        ];
    }

    public function getScreenshot(): ?string
    {
        $screenshotPath = $this->getPath().'/screenshot.png';

        return file_exists($screenshotPath) ? $screenshotPath : null;
    }

    public function getStylesheet(): string
    {
        return $this->getLowerName();
    }

    public function getTemplate(): string
    {
        return $this->getParentTheme() ?? $this->getLowerName();
    }

    public function getVersion(): string
    {
        return $this->get('Version', '1.0.0');
    }

    public function getAuthor(): string
    {
        return $this->get('Author', '');
    }

    public function getThemeUri(): ?string
    {
        return $this->get('ThemeURI');
    }

    public function getAuthorUri(): ?string
    {
        return $this->get('AuthorURI');
    }

    public function isChildTheme(): bool
    {
        return ! empty($this->getParentTheme());
    }

    public function getParentTheme(): ?string
    {
        return $this->get('Template');
    }

    public function getHeaders(): array
    {
        return $this->themeHeaders;
    }

    public function setHeaders(array $headers): static
    {
        $this->themeHeaders = $headers;

        // Map headers to metadata
        foreach ($headers as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getRootNamespace(): string
    {
        return 'Theme';
    }

    public function getNamespace(): string
    {
        return $this->getRootNamespace().'\\'.$this->normalizeThemeName($this->getLowerName());
    }

    /**
     * Normalize theme name to be PSR-4 compliant.
     */
    protected function normalizeThemeName(string $themeName): string
    {
        return str_replace(['-', '_', ' '], '', ucwords($themeName, '-_ '));
    }
}
