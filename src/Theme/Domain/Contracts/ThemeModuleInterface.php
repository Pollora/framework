<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Contracts;

use Pollora\Modules\Domain\Contracts\ModuleInterface;

interface ThemeModuleInterface extends ModuleInterface
{
    /**
     * Get WordPress theme data.
     */
    public function getThemeData(): array;

    /**
     * Get theme screenshot path.
     */
    public function getScreenshot(): ?string;

    /**
     * Get theme stylesheet directory.
     */
    public function getStylesheet(): string;

    /**
     * Get theme template directory.
     */
    public function getTemplate(): string;

    /**
     * Get theme version.
     */
    public function getVersion(): string;

    /**
     * Get theme author.
     */
    public function getAuthor(): string;

    /**
     * Get theme URI.
     */
    public function getThemeUri(): ?string;

    /**
     * Get author URI.
     */
    public function getAuthorUri(): ?string;

    /**
     * Check if this is a child theme.
     */
    public function isChildTheme(): bool;

    /**
     * Get parent theme (if child theme).
     */
    public function getParentTheme(): ?string;

    /**
     * Get theme headers.
     */
    public function getHeaders(): array;
}
