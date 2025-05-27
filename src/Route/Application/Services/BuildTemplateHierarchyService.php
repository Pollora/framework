<?php

declare(strict_types=1);

namespace Pollora\Route\Application\Services;

use Pollora\Route\Domain\Contracts\TemplateResolverInterface;
use Pollora\Route\Domain\Models\TemplateHierarchy;
use Pollora\Route\Domain\Services\WordPressContextBuilder;

/**
 * Service for building WordPress template hierarchy
 *
 * Orchestrates the creation of template hierarchy based on WordPress
 * conditional logic and current request context.
 */
final class BuildTemplateHierarchyService
{
    public function __construct(
        private readonly TemplateResolverInterface $templateResolver,
        private readonly WordPressContextBuilder $contextBuilder,
        private readonly array $config = []
    ) {}

    /**
     * Build template hierarchy for the current context
     *
     * @param array $context Request context including WordPress globals
     * @return TemplateHierarchy The built template hierarchy
     */
    public function execute(array $context = []): TemplateHierarchy
    {
        // Enhance context with WordPress information
        $enhancedContext = $this->enhanceContext($context);

        // Resolve base hierarchy
        $hierarchy = $this->templateResolver->resolveHierarchy($enhancedContext);

        // Apply filters for customization
        $hierarchy = $this->applyHierarchyFilters($hierarchy, $enhancedContext);

        return $hierarchy;
    }

    /**
     * Build hierarchy for a specific WordPress condition
     *
     * @param string $condition WordPress conditional (e.g., 'is_page')
     * @param array $parameters Condition parameters
     * @param array $context Additional context
     * @return TemplateHierarchy The built hierarchy
     */
    public function forCondition(string $condition, array $parameters = [], array $context = []): TemplateHierarchy
    {
        $contextWithCondition = array_merge($context, [
            'condition_parameters' => $parameters,
        ]);

        return $this->execute($contextWithCondition);
    }

    /**
     * Build hierarchy for a specific post
     *
     * @param int $postId Post ID
     * @param array $context Additional context
     * @return TemplateHierarchy The built hierarchy
     */
    public function forPost(int $postId, array $context = []): TemplateHierarchy
    {
        $contextWithPost = $this->contextBuilder->buildPostContext($postId, $context);
        return $this->execute($contextWithPost);
    }

    /**
     * Build hierarchy for a specific taxonomy term
     *
     * @param int $termId Term ID
     * @param string $taxonomy Taxonomy name
     * @param array $context Additional context
     * @return TemplateHierarchy The built hierarchy
     */
    public function forTerm(int $termId, string $taxonomy, array $context = []): TemplateHierarchy
    {
        $contextWithTerm = $this->contextBuilder->buildTermContext($termId, $taxonomy, $context);
        return $this->execute($contextWithTerm);
    }

    /**
     * Build hierarchy for an archive page
     *
     * @param string $postType Post type for the archive
     * @param array $context Additional context
     * @return TemplateHierarchy The built hierarchy
     */
    public function forArchive(string $postType = '', array $context = []): TemplateHierarchy
    {
        $contextWithArchive = $this->contextBuilder->buildArchiveContext($postType, $context);
        return $this->execute($contextWithArchive);
    }

    /**
     * Get template candidates for debugging
     *
     * @param array $context Request context
     * @return array Array of template candidates with their priorities
     */
    public function getTemplateCandidates(array $context = []): array
    {
        $hierarchy = $this->execute($context);
        $templates = $hierarchy->getTemplatesInOrder();
        $candidates = [];

        foreach ($templates as $index => $template) {
            $candidates[] = [
                'template' => $template,
                'priority' => count($templates) - $index,
                'exists' => $this->templateResolver->templateExists($template),
                'paths' => $this->getTemplatePaths($template),
            ];
        }

        return $candidates;
    }

    /**
     * Check if a specific template would be used
     *
     * @param string $template Template name to check
     * @param array $context Request context
     * @return bool True if template would be used
     */
    public function wouldUseTemplate(string $template, array $context = []): bool
    {
        $hierarchy = $this->execute($context);
        $foundTemplate = $this->templateResolver->findTemplate($hierarchy);

        return $foundTemplate === $template;
    }

    /**
     * Enhance context with WordPress information
     *
     * @param array $context Base context
     * @return array Enhanced context
     */
    private function enhanceContext(array $context): array
    {
        return $this->contextBuilder->buildContext($context);
    }

    /**
     * Apply WordPress filters to customize hierarchy
     *
     * @param TemplateHierarchy $hierarchy Base hierarchy
     * @param array $context Request context
     * @return TemplateHierarchy Filtered hierarchy
     */
    private function applyHierarchyFilters(TemplateHierarchy $hierarchy, array $context): TemplateHierarchy
    {
        if (!function_exists('apply_filters')) {
            return $hierarchy;
        }

        // Apply template hierarchy filter
        $templates = $hierarchy->getTemplatesInOrder();
        $filteredTemplates = apply_filters('pollora/template_hierarchy', $templates, $context);

        if ($filteredTemplates !== $templates) {
            $hierarchy = $hierarchy->withTemplates($filteredTemplates);
        }

        // Apply condition-specific filters
        $condition = $hierarchy->getCondition();
        $conditionTemplates = apply_filters("pollora/template_hierarchy/{$condition}", $templates, $context);

        if ($conditionTemplates !== $templates) {
            $hierarchy = $hierarchy->withTemplates($conditionTemplates);
        }

        return $hierarchy;
    }

    /**
     * Get possible paths for a template
     *
     * @param string $template Template name
     * @return array Array of possible file paths
     */
    private function getTemplatePaths(string $template): array
    {
        $extensions = $this->templateResolver->getTemplateExtensions();
        $searchPaths = $this->templateResolver->getTemplatePaths();

        $paths = [];

        foreach ($searchPaths as $searchPath) {
            foreach ($extensions as $extension) {
                $paths[] = rtrim($searchPath, '/') . '/' . $template . $extension;
            }
        }

        return $paths;
    }
}
