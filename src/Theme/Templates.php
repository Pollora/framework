<?php

declare(strict_types=1);

namespace Pollen\Theme;

use Pollen\Support\Facades\Action;
use Pollen\Theme\Contracts\ThemeComponent;

/**
 * Class Templates
 *
 * This class is responsible for registering theme templates and retrieving page templates for a specific post type.
 */
class Templates implements ThemeComponent
{
    public function register(): void
    {
        Action::add('theme_page_templates', [$this, 'registerTemplates'], 10, 3);
    }

    /**
     * Register all of the site's theme templates.
     *
     * @return array An associative array of page templates, where the keys are the template slugs
     */
    public function registerTemplates($pageTemplates, $wp_themes, $post): array
    {
        $themePageTemplates = $this->getThemePageTemplates($post->post_type);

        return array_merge($pageTemplates, $themePageTemplates);
    }

    /**
     * Retrieves the page templates available for a specific post type.
     *
     * @param  string  $postType  The post type for which to retrieve the page templates.
     * @return array An associative array of page templates, where the keys are the template slugs
     *               and the values are the template labels.
     */
    public function getThemePageTemplates($postType)
    {
        $configPageTemplates = (array) config('theme.templates');

        $pageTemplates = [];

        foreach ($configPageTemplates as $slug => $template) {
            if (! isset($template['post_types']) || ! in_array($postType, (array) $template['post_types'])) {
                continue;
            }
            $pageTemplates[$slug] = $template['label'];
        }

        return $pageTemplates;
    }
}
