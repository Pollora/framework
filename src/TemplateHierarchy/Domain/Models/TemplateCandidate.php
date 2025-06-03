<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Domain\Models;

/**
 * Represents a candidate template in the template resolution hierarchy.
 */
class TemplateCandidate
{
    /**
     * Create a new template candidate.
     *
     * @param  string  $type  The template type (e.g. 'php', 'blade')
     * @param  string  $templatePath  The full path to the template or blade view name
     * @param  string  $origin  The template origin (e.g. 'wordpress', 'woocommerce')
     * @param  int  $priority  The priority of this template (lower number = higher priority)
     */
    public function __construct(
        public readonly string $type,
        public readonly string $templatePath,
        public readonly string $origin,
        public readonly int $priority = 10
    ) {}

    /**
     * Create a clone of this candidate with a different priority.
     */
    public function withPriority(int $priority): self
    {
        return new self(
            $this->type,
            $this->templatePath,
            $this->origin,
            $priority
        );
    }

    /**
     * Check if this template exists on disk.
     */
    public function exists(): bool
    {
        if ($this->type === 'blade') {
            // Blade templates are handled by the renderer
            return true;
        }

        return file_exists($this->templatePath) && is_readable($this->templatePath);
    }
}
