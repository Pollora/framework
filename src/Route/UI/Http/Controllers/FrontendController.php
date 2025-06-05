<?php

declare(strict_types=1);

namespace Pollora\Route\UI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

/**
 * Frontend controller for WordPress template fallback.
 *
 * This controller handles requests that don't match any defined routes
 * by falling back to WordPress template hierarchy. Special WordPress requests
 * (robots.txt, favicon, feeds, trackbacks) are handled earlier in the WordPress
 * bootstrap process and will not reach this controller.
 *
 * The controller respects wp_using_themes() condition and implements the same
 * template hierarchy logic as WordPress's template-loader.php but using
 * Laravel's View system instead of PHP includes.
 */
class FrontendController
{
    public function __construct(
        private readonly TemplateFinderInterface $templateFinder
    ) {}

    /**
     * Handle the request using WordPress template hierarchy.
     */
    public function handle(Request $request): Response
    {
        // Early return if themes are not being used
        if (function_exists('wp_using_themes') && ! wp_using_themes()) {
            abort(404, 'Themes are disabled');
        }

        $templatePath = $this->getTemplateFile();

        // Convert file path to Laravel view name
        $viewName = $this->templateFinder->getViewNameFromPath($templatePath);

        if ($viewName && View::exists($viewName)) {
            return response(View::make($viewName));
        }

        if (file_exists($templatePath)) {
            ob_start();
            include $templatePath;
            $content = ob_get_clean();

            return response($content);
        }

        // If no template found, return 404
        abort(404);
    }

    /**
     * Get template hierarchy using WordPress filters.
     *
     * This approach is inspired by Sage Acorn and uses WordPress's own
     * template hierarchy system with filters.
     */
    protected function getTemplateFile(): string
    {
        if (wp_using_themes()) {

            $tag_templates = [
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
            $template = false;

            // Loop through each of the template conditionals, and find the appropriate template file.
            foreach ($tag_templates as $tag => $template_getter) {
                if (call_user_func($tag)) {
                    $template = call_user_func($template_getter);
                }

                if ($template) {
                    if ($tag === 'is_attachment') {
                        remove_filter('the_content', 'prepend_attachment');
                    }

                    break;
                }
            }

            if (! $template) {
                $template = get_index_template();
            }

            /**
             * Filters the path of the current template before including it.
             *
             * @since 3.0.0
             *
             * @param  string  $template  The path of the template to include.
             */
            return apply_filters('template_include', $template);
        }

        return '';
    }
}
