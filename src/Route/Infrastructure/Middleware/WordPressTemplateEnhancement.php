<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pollora\Route\Infrastructure\Providers\RouteServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Middleware giving Blade responses WordPress's template enhancement output buffer.
 *
 * Since WordPress 6.9, `template-loader.php` fires `wp_before_include_template`
 * just before including the theme template, and `wp_start_template_enhancement_output_buffer()`
 * starts an output buffer there. When the page is complete, the buffer runs the
 * `wp_template_enhancement_output_buffer` filter over the whole HTML document.
 *
 * WordPress 7 relies on it for classic themes: block styles load on demand and
 * are printed at `wp_footer`, once the blocks of the page are known, then
 * `wp_hoist_late_printed_styles()` moves them — `global-styles` included — back
 * into the `<head>` through that filter. Pollora renders Blade views into a
 * Laravel response and never includes a template, so the buffer never started:
 * those styles stayed at the bottom of every page, after the content they style.
 *
 * This middleware plays the buffer's part on the response instead of PHP's
 * output: it fires `wp_template_enhancement_output_buffer_started` before the
 * view renders, then applies the filter and the `wp_finalized_template_enhancement_output_buffer`
 * action to the response content. Any plugin using that filter — not only
 * style hoisting — now sees Pollora's pages.
 *
 * @see RouteServiceProvider::WORDPRESS_MIDDLEWARE
 * @see wp_start_template_enhancement_output_buffer()
 * @see wp_finalize_template_enhancement_output_buffer()
 */
class WordPressTemplateEnhancement
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware handler in the pipeline
     * @return mixed The HTTP response, its HTML passed through the enhancement filter
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->shouldEnhance()) {
            return $next($request);
        }

        do_action('wp_template_enhancement_output_buffer_started');

        $response = $next($request);

        if ($this->canEnhance($response)) {
            $this->enhance($response);
        }

        return $response;
    }

    /**
     * Whether WordPress wants the template output enhanced.
     *
     * The answer is WordPress's own, so a site that opted out — to stream its
     * responses — keeps them as they are. `wp_styles()` is called first: the
     * classic theme hooks are added at `wp_default_styles`, which only fires
     * when the styles registry is created.
     *
     * @return bool True if the response should go through the enhancement filter
     */
    private function shouldEnhance(): bool
    {
        if (! function_exists('wp_should_output_buffer_template_for_enhancement')
            || ! function_exists('wp_styles')
            || ! function_exists('do_action')
            || ! function_exists('apply_filters')) {
            return false;
        }

        wp_styles();

        return wp_should_output_buffer_template_for_enhancement();
    }

    /**
     * Check if the response holds an HTML document that can be rewritten.
     *
     * @param  mixed  $response  The response to evaluate
     * @return bool True for a buffered HTML response
     */
    private function canEnhance(mixed $response): bool
    {
        if (! $response instanceof SymfonyResponse) {
            return false;
        }

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        if ($response->isRedirection() || $response->isInformational() || $response->isEmpty()) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        return $contentType === ''
            || str_contains($contentType, 'text/html')
            || str_contains($contentType, 'application/xhtml+xml');
    }

    /**
     * Run the response content through the enhancement filter and action.
     *
     * Like WordPress, a callback that throws leaves the page as it was rendered
     * rather than breaking it: the error is reported and the original HTML sent.
     *
     * @param  SymfonyResponse  $response  The response to rewrite
     */
    private function enhance(SymfonyResponse $response): void
    {
        $content = $response->getContent();

        if ($content === false || $content === '') {
            return;
        }

        try {
            $enhanced = (string) apply_filters('wp_template_enhancement_output_buffer', $content, $content);
            do_action('wp_finalized_template_enhancement_output_buffer', $enhanced);
        } catch (\Throwable $throwable) {
            report($throwable);

            return;
        }

        $response->setContent($enhanced);
    }
}
