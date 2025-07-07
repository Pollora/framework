<?php

declare(strict_types=1);

namespace Pollora\View\Application\UseCases;

use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\View\Domain\Contracts\TemplateHierarchyFilterInterface;

/**
 * Use case for registering WordPress template hierarchy filters.
 *
 * This use case orchestrates the registration of all necessary WordPress
 * filters to integrate Blade templates with the WordPress template hierarchy.
 */
class RegisterTemplateHierarchyFiltersUseCase
{
    public function __construct(
        private readonly Filter $filter,
        private readonly TemplateHierarchyFilterInterface $hierarchyFilter
    ) {}

    /**
     * Execute the use case to register all template hierarchy filters.
     */
    public function execute(): void
    {
        if (! function_exists('add_filter')) {
            return;
        }

        // Main template resolution filter
        $this->filter->add(
            'template_include',
            $this->hierarchyFilter->resolveTemplateInclude(...),
            100
        );

        // Template hierarchy filters - inject Blade templates
        $hierarchyFilters = [
            'index_template_hierarchy',
            '404_template_hierarchy',
            'archive_template_hierarchy',
            'author_template_hierarchy',
            'category_template_hierarchy',
            'tag_template_hierarchy',
            'taxonomy_template_hierarchy',
            'date_template_hierarchy',
            'home_template_hierarchy',
            'frontpage_template_hierarchy',
            'page_template_hierarchy',
            'paged_template_hierarchy',
            'search_template_hierarchy',
            'single_template_hierarchy',
            'singular_template_hierarchy',
            'attachment_template_hierarchy',
            'privacypolicy_template_hierarchy',
            'embed_template_hierarchy',
        ];

        foreach ($hierarchyFilters as $filterName) {
            $this->filter->add(
                $filterName,
                $this->hierarchyFilter->extendTemplateHierarchy(...),
                10
            );
        }

        // Theme templates filter
        $this->filter->add(
            'theme_templates',
            $this->hierarchyFilter->extendThemeTemplates(...),
            100,
            4
        );
    }
}
