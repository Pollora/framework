<?php

declare(strict_types=1);

namespace Pollora\Dashboard\Domain\Contracts;

/**
 * What the dashboard reports about the WordPress it runs in.
 *
 * Each method answers null (or its empty value) when WordPress is not loaded
 * or cannot tell, so the collector decides alone how to show a missing value.
 */
interface WordPressEnvironmentInterface
{
    /**
     * The site locale, or null when WordPress cannot resolve it yet.
     */
    public function locale(): ?string;

    /**
     * The WordPress version, or null when WordPress is not loaded.
     */
    public function version(): ?string;

    public function isMultisite(): bool;

    /**
     * The permalink structure, or an empty string for plain permalinks.
     */
    public function permalinkStructure(): string;

    /**
     * The plural label of a registered post type, or null when it has none.
     */
    public function postTypeLabel(string $slug): ?string;

    /**
     * The plural label of a registered taxonomy, or null when it has none.
     */
    public function taxonomyLabel(string $slug): ?string;

    /**
     * The active theme, or null when WordPress is not loaded.
     *
     * @return array{name: string, version: string, template: string}|null
     */
    public function activeTheme(): ?array;
}
