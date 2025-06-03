<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Infrastructure\Services;

use Illuminate\Contracts\Config\Repository;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\TemplateHierarchy\Domain\Contracts\TemplateResolverInterface;
use Pollora\TemplateHierarchy\Infrastructure\Resolvers\WordPressTemplateResolver;

/**
 * WordPress template source providing core WordPress templates.
 */
class WordPressTemplateSource extends AbstractTemplateSource
{
    /**
     * Create a new WordPress template source.
     */
    public function __construct(
        private readonly Repository $config,
        private readonly Filter $filter
    ) {
        $this->name = 'wordpress';
        $this->priority = 20; // Lower priority than plugin sources
    }

    /**
     * Get the template resolvers for WordPress templates.
     *
     * @return TemplateResolverInterface[]
     */
    public function getResolvers(): array
    {
        $tagTemplates = $this->getTagTemplatesOrder();
        $resolvers = [];

        foreach ($tagTemplates as $condition => $templateFunction) {
            $resolvers[] = new WordPressTemplateResolver(
                $condition,
                $this->config,
                $this->filter
            );
        }

        return $resolvers;
    }

    /**
     * Get the template loading order as defined in WordPress.
     *
     * @return array<string, string> Mapping of conditional tags to template getter functions
     */
    private function getTagTemplatesOrder(): array
    {
        return [
            'is_embed' => 'get_embed_template',
            'is_404' => 'get_404_template',
            'is_search' => 'get_search_template',
            'is_front_page' => 'get_front_page_template',
            'is_home' => 'get_home_template',
            'is_privacy_policy' => 'get_privacy_policy_template',
            'is_post_type_archive' => 'get_post_type_archive_template',
            'is_tax' => 'get_taxonomy_template',
            'is_attachment' => 'get_attachment_template',
            'is_single' => 'get_single_template',
            'is_page' => 'get_page_template',
            'is_singular' => 'get_singular_template',
            'is_category' => 'get_category_template',
            'is_tag' => 'get_tag_template',
            'is_author' => 'get_author_template',
            'is_date' => 'get_date_template',
            'is_archive' => 'get_archive_template',
        ];
    }
}
