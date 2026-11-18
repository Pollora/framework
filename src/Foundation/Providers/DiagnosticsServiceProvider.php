<?php

declare(strict_types=1);

namespace Pollora\Foundation\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\ServiceProvider;
use Pollora\Foundation\Support\RequestDiagnostics;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\WordPress\WordPressServiceProvider;

/**
 * Reports what a request cost, to the performance gates, and only to them.
 *
 * Nothing is reported unless WP_DEBUG is on and the request sends
 * `X-Pollora-Diagnostics: 1` (see {@see RequestDiagnostics}). A request without
 * the header registers no hook at all; one with it, on a site where WP_DEBUG is
 * off, registers them but each one answers nothing.
 *
 * - A REST response carries the report in the `X-Pollora-Diagnostics` header.
 * - An HTML page carries it in a trailing comment written at `shutdown`, after
 *   WordPress has flushed its buffers, so the count covers the whole render.
 *   A header is not possible there: a theme's output has started by then.
 *
 * The hooks are registered in `register()`, not `boot()`: Pollora loads WordPress
 * and runs `wp()` while booting {@see WordPressServiceProvider},
 * and a REST request is answered, then ends, inside that call. A provider booted
 * after it would add its `rest_post_dispatch` filter too late to ever run. WP_DEBUG
 * may not be defined yet at that point, so it is read when a hook runs.
 */
class DiagnosticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RequestDiagnostics::class);

        if (RequestDiagnostics::asksForReport($_SERVER)) {
            $this->hookMeasures();
        }
    }

    /**
     * Hook the measures, for a request that asked for them.
     */
    private function hookMeasures(): void
    {
        $diagnostics = $this->app->make(RequestDiagnostics::class);
        $start = RequestDiagnostics::startedAt($_SERVER, microtime(true));

        $this->app->make('events')->listen(QueryExecuted::class, static function () use ($diagnostics): void {
            $diagnostics->recordLaravelQuery();
        });

        $action = $this->app->make(Action::class);
        $filter = $this->app->make(Filter::class);

        // WordPress is loaded after this provider registers: wp_loaded has not fired yet.
        $action->add('wp_loaded', static function () use ($diagnostics, $start): void {
            $diagnostics->recordBoot(microtime(true), $start);
        }, PHP_INT_MAX);

        $filter->add('rest_post_dispatch', function (mixed $response) use ($diagnostics, $start): mixed {
            if ($this->reporting() && is_object($response) && method_exists($response, 'header')) {
                $response->header(RequestDiagnostics::HEADER, RequestDiagnostics::headerValue($this->report($diagnostics, $start)));
            }

            return $response;
        }, PHP_INT_MAX);

        $action->add('shutdown', function () use ($diagnostics, $start): void {
            if (! $this->reporting() || ! RequestDiagnostics::isHtmlContext($this->isRestRequest(), $this->isAdminRequest(), $this->isAjaxRequest())) {
                return;
            }

            if (RequestDiagnostics::acceptsHtmlComment(headers_list(), http_response_code())) {
                echo RequestDiagnostics::htmlComment($this->report($diagnostics, $start));
            }
        }, PHP_INT_MAX);
    }

    /**
     * Whether WordPress runs in debug mode. A method so a test can answer
     * without defining WP_DEBUG for the rest of the process.
     */
    protected function debugging(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Whether this request may be reported on, now that WP_DEBUG is known.
     */
    private function reporting(): bool
    {
        return RequestDiagnostics::isRequested($this->debugging(), $_SERVER);
    }

    /**
     * Whether WordPress is answering a REST request.
     */
    protected function isRestRequest(): bool
    {
        return defined('REST_REQUEST');
    }

    /**
     * Whether this is a screen of the administration.
     */
    protected function isAdminRequest(): bool
    {
        return function_exists('is_admin') && is_admin();
    }

    /**
     * Whether this is an admin-ajax request.
     */
    protected function isAjaxRequest(): bool
    {
        return function_exists('wp_doing_ajax') && wp_doing_ajax();
    }

    /**
     * The report as of now.
     *
     * @return array<string, mixed>
     */
    private function report(RequestDiagnostics $diagnostics, float $start): array
    {
        global $wpdb;

        return $diagnostics->report((int) ($wpdb->num_queries ?? 0), microtime(true), $start);
    }
}
