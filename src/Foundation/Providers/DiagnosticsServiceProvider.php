<?php

declare(strict_types=1);

namespace Pollora\Foundation\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\ServiceProvider;
use Pollora\Foundation\Support\RequestDiagnostics;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;

/**
 * Reports what a request cost, to the performance gates, and only to them.
 *
 * Off unless WP_DEBUG is on and the request sends `X-Pollora-Diagnostics: 1`
 * (see {@see RequestDiagnostics}): the production path registers nothing.
 *
 * - A REST response carries the report in the `X-Pollora-Diagnostics` header.
 * - An HTML page carries it in a trailing comment written at `shutdown`, after
 *   WordPress has flushed its buffers, so the count covers the whole render.
 *   A header is not possible there: a theme's output has started by then.
 */
class DiagnosticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RequestDiagnostics::class);
    }

    /**
     * Hook the measures, when the request asked for them.
     */
    public function boot(): void
    {
        if (! RequestDiagnostics::isRequested($this->debugging(), $_SERVER)) {
            return;
        }

        $diagnostics = $this->app->make(RequestDiagnostics::class);
        $start = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));

        $this->app->make('events')->listen(QueryExecuted::class, static function () use ($diagnostics): void {
            $diagnostics->recordLaravelQuery();
        });

        $action = $this->app->make(Action::class);
        $filter = $this->app->make(Filter::class);

        // Pollora boots before wp_loaded; if it did not, the boot is as long as it has been.
        if (function_exists('did_action') && did_action('wp_loaded') > 0) {
            $diagnostics->recordBoot(microtime(true), $start);
        } else {
            $action->add('wp_loaded', static function () use ($diagnostics, $start): void {
                $diagnostics->recordBoot(microtime(true), $start);
            }, PHP_INT_MAX);
        }

        $filter->add('rest_post_dispatch', static function (mixed $response) use ($diagnostics, $start): mixed {
            if (is_object($response) && method_exists($response, 'header')) {
                $response->header(RequestDiagnostics::HEADER, RequestDiagnostics::headerValue(self::report($diagnostics, $start)));
            }

            return $response;
        }, PHP_INT_MAX);

        $action->add('shutdown', static function () use ($diagnostics, $start): void {
            if (defined('REST_REQUEST') || (function_exists('is_admin') && is_admin()) || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
                return;
            }

            if (RequestDiagnostics::acceptsHtmlComment(headers_list(), http_response_code())) {
                echo RequestDiagnostics::htmlComment(self::report($diagnostics, $start));
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
     * The report as of now.
     *
     * @return array<string, mixed>
     */
    private static function report(RequestDiagnostics $diagnostics, float $start): array
    {
        global $wpdb;

        return $diagnostics->report((int) ($wpdb->num_queries ?? 0), microtime(true), $start);
    }
}
