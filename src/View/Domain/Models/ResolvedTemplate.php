<?php

declare(strict_types=1);

namespace Pollora\View\Domain\Models;

/**
 * Represents a successfully resolved template.
 *
 * This value object encapsulates the result of template resolution,
 * including the template name, file path, and whether it's a Blade template.
 */
class ResolvedTemplate
{
    public function __construct(
        private readonly string $templateName,
        private readonly string $filePath,
        private readonly bool $isBlade
    ) {
        if (empty($templateName)) {
            throw new \InvalidArgumentException('Template name cannot be empty');
        }

        if (empty($filePath)) {
            throw new \InvalidArgumentException('File path cannot be empty');
        }
    }

    /**
     * Get the template name (e.g., 'page-about.blade.php').
     */
    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    /**
     * Get the full file path to the template.
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Check if this is a Blade template.
     */
    public function isBlade(): bool
    {
        return $this->isBlade;
    }

    /**
     * Check if this is a PHP template.
     */
    public function isPhp(): bool
    {
        return ! $this->isBlade;
    }

    /**
     * Get the view name for Blade rendering (without .blade.php extension).
     *
     * Returns null if this is not a Blade template.
     */
    public function getViewName(): ?string
    {
        if (! $this->isBlade) {
            return null;
        }

        return str_replace('.blade.php', '', $this->templateName);
    }

    /**
     * Get the base template name without extension.
     */
    public function getBaseTemplateName(): string
    {
        if ($this->isBlade) {
            return str_replace('.blade.php', '', $this->templateName);
        }

        return str_replace('.php', '', $this->templateName);
    }

    /**
     * Create a ResolvedTemplate from a template name and file path.
     * Automatically detects if it's a Blade template.
     */
    public static function fromPath(string $templateName, string $filePath): self
    {
        $isBlade = str_ends_with($templateName, '.blade.php');

        return new self($templateName, $filePath, $isBlade);
    }

    /**
     * Get template metadata for debugging/logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'template_name' => $this->templateName,
            'file_path' => $this->filePath,
            'is_blade' => $this->isBlade,
            'view_name' => $this->getViewName(),
            'base_name' => $this->getBaseTemplateName(),
        ];
    }
}
