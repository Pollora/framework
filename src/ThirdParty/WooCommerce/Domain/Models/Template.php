<?php

declare(strict_types=1);

namespace Pollora\ThirdParty\WooCommerce\Domain\Models;

/**
 * Value object representing a WooCommerce template.
 *
 * This class encapsulates template information and provides
 * methods for template path manipulation and validation.
 */
final readonly class Template
{
    public function __construct(
        public string $path,
        public string $name = '',
        public bool $isBladeTemplate = false
    ) {}

    /**
     * Create a Template instance from a file path.
     */
    public static function fromPath(string $path): self
    {
        $name = basename($path, '.php');
        $isBladeTemplate = str_ends_with($path, '.blade.php');

        return new self($path, $name, $isBladeTemplate);
    }

    /**
     * Get the relative template path for WooCommerce.
     */
    public function getRelativePath(array $defaultPaths = []): string
    {
        return str_replace($defaultPaths, '', $this->path);
    }

    /**
     * Check if this template is a WooCommerce template.
     */
    public function isWooCommerceTemplate(array $defaultPaths = []): bool
    {
        return $this->getRelativePath($defaultPaths) !== $this->path;
    }

    /**
     * Convert template to Blade equivalent.
     */
    public function toBladeTemplate(): self
    {
        if ($this->isBladeTemplate || ! str_ends_with($this->path, '.php')) {
            return $this;
        }

        $bladePath = str_replace('.php', '.blade.php', $this->path);

        return new self($bladePath, $this->name, true);
    }

    /**
     * Get view name for Laravel view factory.
     */
    public function getViewName(): string
    {
        if (! $this->isBladeTemplate) {
            return '';
        }

        // Convert path to view name: woocommerce/single-product.blade.php -> woocommerce.single-product
        $viewName = str_replace(['/', '.blade.php'], ['.', ''], $this->getRelativePath());

        return trim($viewName, '.');
    }
}
