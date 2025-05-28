<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Models;

use Pollora\Route\Domain\Contracts\TemplateResolverInterface;
use function Symfony\Component\String\s;

/**
 * Value object representing WordPress template hierarchy
 *
 * Encapsulates the WordPress template hierarchy logic and provides
 * methods to determine template priority and specificity.
 */
final class TemplateHierarchy
{
    private function __construct(
        private readonly array $templates,
        private readonly string $condition,
        private readonly int $priority,
        private readonly array $context = [],
        private readonly ?TemplateResolverInterface $templateResolver = null
    ) {}

    /**
     * Create template hierarchy from WordPress native hierarchy
     */
    public static function fromWordPressHierarchy(
        string $slug,
        bool $isCustom = false,
        string $prefix = '',
        ?TemplateResolverInterface $templateResolver = null
    ): self {
        $templates = self::buildTemplateHierarchy($slug, $isCustom, $prefix);
        $priority = self::calculatePriority($slug, $isCustom, $templates);

        return new self(
            templates: $templates,
            condition: $slug,
            priority: $priority,
            context: [
                'slug' => $slug,
                'is_custom' => $isCustom,
                'prefix' => $prefix,
            ],
            templateResolver: $templateResolver
        );
    }

    /**
     * Create template hierarchy directly from templates array
     */
    public static function fromTemplatesArray(
        array $templates,
        string $condition,
        ?TemplateResolverInterface $templateResolver = null
    ): self {
        $priority = self::calculatePriorityFromTemplates($templates);

        return new self(
            templates: $templates,
            condition: $condition,
            priority: $priority,
            context: [
                'slug' => $condition,
                'is_custom' => false,
                'prefix' => '',
            ],
            templateResolver: $templateResolver
        );
    }

    /**
     * Create from WordPress context
     */
    public static function fromContext(array $context, ?TemplateResolverInterface $templateResolver = null): self
    {
        $slug = self::determineSlugFromContext($context);
        $isCustom = $context['is_custom'] ?? false;
        $prefix = $context['template_prefix'] ?? '';

        return self::fromWordPressHierarchy($slug, $isCustom, $prefix, $templateResolver);
    }

    /**
     * Get templates in priority order (most specific first)
     */
    public function getTemplatesInOrder(): array
    {
        return $this->templates;
    }

    /**
     * Get the primary template (first one that actually exists)
     *
     * Returns the first template in the hierarchy that actually exists,
     * checking both Blade (.blade.php) and PHP (.php) extensions.
     * Falls back to the most specific template if no resolver is available.
     */
    public function getPrimaryTemplate(): ?string
    {
        // If no template resolver is available, return the most specific template
        if ($this->templateResolver === null) {
            return $this->templates[0] ?? null;
        }

        // Check each template in hierarchy order for existence
        foreach ($this->templates as $template) {
            if ($this->templateExists($template)) {
                return $template;
            }
        }

        // If no templates exist, return the most specific one anyway
        // (this maintains backward compatibility)
        return $this->templates[0] ?? null;
    }

    /**
     * Get the condition that generated this hierarchy
     */
    public function getCondition(): string
    {
        return $this->condition;
    }

    /**
     * Get the priority score
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get the context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Check if a specific template exists in this hierarchy
     */
    public function hasTemplate(string $template): bool
    {
        return in_array($template, $this->templates, true);
    }

    /**
     * Get template with extension handling
     */
    public function getTemplatesWithExtensions(array $extensions = ['.blade.php', '.php']): array
    {
        $templatesWithExtensions = [];

        foreach ($this->templates as $template) {
            foreach ($extensions as $extension) {
                $templatesWithExtensions[] = $template . $extension;
            }
        }

        return $templatesWithExtensions;
    }

    /**
     * Check if a template exists using the template resolver
     *
     * Checks for both Blade (.blade.php) and PHP (.php) extensions,
     * with Blade taking priority.
     */
    private function templateExists(string $template): bool
    {
        if ($this->templateResolver === null) {
            return false;
        }

        // Check for Blade template first (.blade.php)
        if ($this->templateResolver->templateExists($template . '.blade.php')) {
            return true;
        }

        // Fall back to PHP template (.php)
        return $this->templateResolver->templateExists($template . '.php');
    }

    /**
     * Create a new hierarchy with additional context
     */
    public function withContext(array $context): self
    {
        return new self(
            $this->templates,
            $this->condition,
            $this->priority,
            array_merge($this->context, $context),
            $this->templateResolver
        );
    }

    /**
     * Create a new hierarchy with modified templates
     */
    public function withTemplates(array $templates): self
    {
        return new self(
            $templates,
            $this->condition,
            self::calculatePriorityFromTemplates($templates),
            $this->context,
            $this->templateResolver
        );
    }

    /**
     * Build template hierarchy based on WordPress logic
     */
    private static function buildTemplateHierarchy(
        string $slug,
        bool $isCustom,
        string $prefix
    ): array {
        $templates = [];

        // Use WordPress native function if available
        if (function_exists('get_query_template')) {
            global $wp_query;

            if ($wp_query && method_exists($wp_query, 'get_queried_object')) {
                $object = $wp_query->get_queried_object();

                if ($object) {
                    return self::getWordPressTemplateHierarchy($slug, $object);
                }
            }
        }

        // Fallback to manual hierarchy building
        return self::buildFallbackHierarchy($slug, $isCustom, $prefix);
    }

    /**
     * Get WordPress template hierarchy using native functions
     */
    private static function getWordPressTemplateHierarchy(string $slug, $object): array
    {
        if (function_exists('get_template_hierarchy')) {
            return get_template_hierarchy($slug, is_object($object), '');
        }

        // Fallback implementation
        return self::buildFallbackHierarchy($slug, false, '');
    }

    /**
     * Build fallback hierarchy when WordPress functions are not available
     */
    private static function buildFallbackHierarchy(
        string $slug,
        bool $isCustom,
        string $prefix
    ): array {
        $templates = [];

        if ($prefix) {
            $templates[] = $prefix . '-' . $slug;
        }

        if ($isCustom && function_exists('get_post')) {
            $post = get_post();
            if ($post) {
                $templates[] = $slug . '-' . $post->post_name;
                $templates[] = $slug . '-' . $post->ID;
            }
        }

        $templates[] = $slug;
        $templates[] = 'index';

        return array_unique($templates);
    }

    /**
     * Calculate priority score for the hierarchy
     */
    private static function calculatePriority(
        string $slug,
        bool $isCustom,
        array $templates
    ): int {
        $baseScore = match ($slug) {
            'front-page' => 1000,
            'home' => 900,
            'page' => 800,
            'single' => 700,
            'category' => 600,
            'tag' => 500,
            'archive' => 400,
            '404' => 300,
            'search' => 200,
            'index' => 100,
            default => 150
        };

        // Add custom specificity
        if ($isCustom) {
            $baseScore += 200;
        }

        // Add template count bonus (more specific templates = higher priority)
        $templateBonus = (count($templates) - 1) * 10;

        return $baseScore + $templateBonus;
    }

    /**
     * Calculate priority from templates array
     */
    private static function calculatePriorityFromTemplates(array $templates): int
    {
        $firstTemplate = $templates[0] ?? 'index';

        $baseScore = match (true) {
            str_contains($firstTemplate, 'front-page') => 1000,
            str_contains($firstTemplate, 'home') => 900,
            str_contains($firstTemplate, 'page-') => 850,
            str_contains($firstTemplate, 'page') => 800,
            str_contains($firstTemplate, 'single-') => 750,
            str_contains($firstTemplate, 'single') => 700,
            str_contains($firstTemplate, 'category-') => 650,
            str_contains($firstTemplate, 'category') => 600,
            str_contains($firstTemplate, 'tag-') => 550,
            str_contains($firstTemplate, 'tag') => 500,
            str_contains($firstTemplate, 'archive') => 400,
            str_contains($firstTemplate, '404') => 300,
            str_contains($firstTemplate, 'search') => 200,
            default => 100
        };

        return $baseScore + (count($templates) * 10);
    }

    /**
     * Determine slug from WordPress context
     */
    private static function determineSlugFromContext(array $context): string
    {
        // Try to determine from WordPress conditional functions
        if (function_exists('is_front_page') && is_front_page()) {
            return 'front-page';
        }

        if (function_exists('is_home') && is_home()) {
            return 'home';
        }

        if (function_exists('is_page') && is_page()) {
            return 'page';
        }

        if (function_exists('is_single') && is_single()) {
            return 'single';
        }

        if (function_exists('is_category') && is_category()) {
            return 'category';
        }

        if (function_exists('is_tag') && is_tag()) {
            return 'tag';
        }

        if (function_exists('is_archive') && is_archive()) {
            return 'archive';
        }

        if (function_exists('is_404') && is_404()) {
            return '404';
        }

        if (function_exists('is_search') && is_search()) {
            return 'search';
        }

        return 'index';
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'templates' => $this->templates,
            'condition' => $this->condition,
            'priority' => $this->priority,
            'context' => $this->context,
            'primary_template' => $this->getPrimaryTemplate(),
        ];
    }
}
