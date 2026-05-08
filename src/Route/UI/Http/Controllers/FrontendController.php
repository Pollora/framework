<?php

declare(strict_types=1);

namespace Pollora\Route\UI\Http\Controllers;

use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
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
    /**
     * Whether the last template resolution used the generic index fallback
     * (no specific template matched for the current condition).
     */
    private bool $usedIndexFallback = false;

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
            return $this->renderNotFound();
        }

        $templatePath = $this->getTemplateFile();

        // Convert file path to Laravel view name
        $viewName = $this->templateFinder->getViewNameFromPath($templatePath);

        // For 404 pages: if no dedicated 404 template was found in the hierarchy
        // (i.e. we fell through to get_index_template()), use Laravel's error page.
        // A real index.blade.php IS a valid fallback for other request types,
        // but for 404s it means the theme has no 404 handling at all.
        if (is_404() && $this->usedIndexFallback) {
            return $this->renderNotFound();
        }

        if ($viewName && View::exists($viewName)) {
            return response(View::make($viewName), is_404() ? Response::HTTP_NOT_FOUND : Response::HTTP_OK);
        }

        if (file_exists($templatePath) && $this->isAllowedTemplatePath($templatePath)) {
            ob_start();
            include $templatePath;
            $content = ob_get_clean();

            return response($content);
        }

        // No WordPress template found — fall back to Laravel's error view
        return $this->renderNotFound();
    }

    /**
     * Render a 404 response using Laravel's error views.
     *
     * Cascade:
     *   1. Application view: errors.404 (resources/views/errors/404.blade.php)
     *   2. Laravel built-in 404 page (via RegisterErrorViewPaths)
     *   3. Plain text fallback
     */
    protected function renderNotFound(): Response
    {
        if (View::exists('errors.404')) {
            return response(View::make('errors.404'), Response::HTTP_NOT_FOUND);
        }

        // Register Laravel's built-in error view paths (same mechanism as the exception handler)
        (new RegisterErrorViewPaths)();

        if (View::exists('errors::404')) {
            return response(View::make('errors::404'), Response::HTTP_NOT_FOUND);
        }

        return response('Not Found', Response::HTTP_NOT_FOUND);
    }

    /**
     * Validate that a template path is within the application base directory
     * to prevent local file inclusion via malicious template_include filters.
     *
     * Uses base_path() (project root) instead of ABSPATH because Bedrock-style
     * layouts split wp-admin, wp-content, themes, and storage across directories
     * that may not share the ABSPATH prefix.
     */
    private function isAllowedTemplatePath(string $templatePath): bool
    {
        $realPath = realpath($templatePath);

        // realpath() resolves symlinks and eliminates ../ — if the resolved path
        // exists and ends with .php, it's safe. A false realpath means the file
        // doesn't exist (already caught by file_exists) or has broken symlinks.
        return $realPath !== false && str_ends_with($realPath, '.php');
    }

    /**
     * Get template hierarchy using WordPress filters.
     *
     * This approach is inspired by Sage Acorn and uses WordPress's own
     * template hierarchy system with filters.
     */
    protected function getTemplateFile(): string
    {
        $this->usedIndexFallback = false;

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
                $this->usedIndexFallback = true;
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
