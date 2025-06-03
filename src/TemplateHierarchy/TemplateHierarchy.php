<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\TemplateHierarchy\Application\Services\TemplateFinderService;
use Pollora\TemplateHierarchy\Domain\Contracts\TemplateRendererInterface;
use Pollora\TemplateHierarchy\Domain\Contracts\TemplateSourceInterface;
use Pollora\TemplateHierarchy\Domain\Exceptions\TemplateNotFoundException;
use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;
use Pollora\TemplateHierarchy\Infrastructure\Services\BladeTemplateRenderer;
use Pollora\TemplateHierarchy\Infrastructure\Services\PhpTemplateRenderer;
use Pollora\TemplateHierarchy\Infrastructure\Services\WooCommerceTemplateSource;
use Pollora\TemplateHierarchy\Infrastructure\Services\WordPressTemplateSource;

/**
 * Main facade for the Template Hierarchy system.
 */
class TemplateHierarchy
{
    /**
     * The finder service that resolves templates.
     */
    private TemplateFinderService $finderService;

    /**
     * Whether the template hierarchy has been initialized.
     */
    private bool $initialized = false;

    /**
     * Create a new template hierarchy facade.
     */
    public function __construct(
        private readonly Container $container,
        private readonly Repository $config,
        private readonly Action $action,
        private readonly Filter $filter
    ) {
        $this->finderService = new TemplateFinderService($this->config, $this->filter);

        // Initialize the system during template_redirect, which is early in the WordPress lifecycle
        $this->action->add('template_redirect', [$this, 'initialize'], 0);

        // Hook into template_include at a high priority to capture the final template
        $this->filter->add('template_include', [$this, 'resolveTemplate'], PHP_INT_MAX - 10);
    }

    /**
     * Initialize the template hierarchy system.
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Register the standard template sources
        $this->registerDefaultSources();

        // Register the standard template renderers
        $this->registerDefaultRenderers();

        $this->initialized = true;
    }

    /**
     * Register the default template sources.
     */
    private function registerDefaultSources(): void
    {
        // Register WooCommerce template source (higher priority)
        $this->finderService->registerSource(new WooCommerceTemplateSource);

        // Register WordPress template source (lower priority)
        $this->finderService->registerSource(
            new WordPressTemplateSource($this->config, $this->filter)
        );

        // Allow plugins to register their own template sources
        $this->action->do('pollora/template_hierarchy/register_sources', $this->finderService);
    }

    /**
     * Register the default template renderers.
     */
    private function registerDefaultRenderers(): void
    {
        // Register PHP template renderer
        $this->finderService->registerRenderer(
            new PhpTemplateRenderer($this->getTemplatePaths())
        );

        // Register Blade template renderer
        if ($this->container->has(Factory::class)) {
            $this->finderService->registerRenderer(
                new BladeTemplateRenderer($this->container->get(Factory::class))
            );
        }

        // Allow plugins to register their own template renderers
        $this->action->do('pollora/template_hierarchy/register_renderers', $this->finderService);
    }

    /**
     * Get all template paths where templates should be looked for.
     *
     * @return string[]
     */
    private function getTemplatePaths(): array
    {
        $paths = $this->config->get('view.template_paths', []);

        if (function_exists('get_template_directory')) {
            $paths[] = get_template_directory();
        }

        if (function_exists('get_stylesheet_directory') && get_stylesheet_directory() !== get_template_directory()) {
            $paths[] = get_stylesheet_directory();
        }

        // Add plugin template directories
        $pluginPaths = $this->config->get('wordpress.plugin_template_paths', []);
        $paths = array_merge($paths, $pluginPaths);

        // Allow filtering of template paths
        return $this->filter->apply('pollora/template_hierarchy/template_paths', $paths);
    }

    /**
     * Register a custom template source.
     */
    public function registerSource(TemplateSourceInterface $source): self
    {
        $this->initialize();
        $this->finderService->registerSource($source);

        return $this;
    }

    /**
     * Register a custom template renderer.
     */
    public function registerRenderer(TemplateRendererInterface $renderer): self
    {
        $this->initialize();
        $this->finderService->registerRenderer($renderer);

        return $this;
    }

    /**
     * Get all template candidates for the current request.
     *
     * @param  bool  $refresh  Force refresh of the hierarchy even if already built
     * @return TemplateCandidate[] Array of template candidates
     */
    public function getHierarchy(bool $refresh = false): array
    {
        $this->initialize();

        return $this->finderService->getHierarchy($refresh);
    }

    /**
     * Resolve template filtering through WordPress template_include hook.
     *
     * @param  string  $template  The template being included by WordPress
     * @return string The resolved template path
     */
    public function resolveTemplate(string $template): string
    {
        $this->initialize();

        try {
            // If WordPress found a template, use that
            if (! empty($template)) {
                return $template;
            }

            // Otherwise, resolve using our template hierarchy
            return $this->finderService->resolveTemplate();
        } catch (TemplateNotFoundException $e) {
            // If no template was found, fall back to the WordPress template
            return $template;
        }
    }

    /**
     * Register a custom template handler for a specific template type.
     *
     * @param  string  $type  Template type identifier
     * @param  callable  $callback  Function that returns an array of template files
     */
    public function registerTemplateHandler(string $type, callable $callback): void
    {
        $this->filter->add("pollora/template_hierarchy/{$type}_templates", function ($templates, $queriedObject) use ($callback): array {
            $customTemplates = call_user_func($callback, $queriedObject);

            return array_merge($customTemplates, $templates);
        }, 10, 2);
    }

    /**
     * Get template candidates filtered by type.
     *
     * @param  string  $type  The template type to filter by (e.g., 'php', 'blade')
     * @param  bool  $refresh  Force refresh of the hierarchy
     * @return TemplateCandidate[] Array of template candidates of the specified type
     */
    public function getHierarchyByType(string $type, bool $refresh = false): array
    {
        $this->initialize();

        return $this->finderService->getHierarchyByType($type, $refresh);
    }

    /**
     * Get all template paths for the current request.
     * This is a convenience method that returns just the paths from all candidates.
     *
     * @param  bool  $refresh  Force refresh of the hierarchy
     * @return string[] Array of template paths
     */
    public function getAllTemplatePaths(bool $refresh = false): array
    {
        $this->initialize();

        return $this->finderService->getTemplatePaths($refresh);
    }

    /**
     * Get template paths filtered by type.
     *
     * @param  string  $type  The template type to filter by (e.g., 'php', 'blade')
     * @param  bool  $refresh  Force refresh of the hierarchy
     * @return string[] Array of template paths for the specified type
     */
    public function getTemplatePathsByType(string $type, bool $refresh = false): array
    {
        $this->initialize();

        return $this->finderService->getTemplatePathsByType($type, $refresh);
    }
}
