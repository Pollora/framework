<?php

declare(strict_types=1);

namespace Pollora\Foundation\Support;

/**
 * What a request cost, reported to the one caller that asked for it.
 *
 * The performance gates of the harness (a query budget per page type, a nightly
 * measure of the boot) need numbers a browser test can read from the outside:
 * how many SQL queries WordPress ran, how many Laravel ran, how long the boot
 * took. The debug bar can say it, but it is a development tool with its own
 * switches and markup, which a gate must not depend on.
 *
 * Nothing is reported unless both conditions hold: WP_DEBUG is on, and the
 * request itself carries the `X-Pollora-Diagnostics: 1` header. A visitor, a
 * crawler or a production site, where WP_DEBUG is off, never sees any of it.
 *
 * The values are computed here from numbers handed in, so the rules (when to
 * report, how to write the report) are testable without WordPress.
 */
final class RequestDiagnostics
{
    /** The header a client sends to ask for the report, and the one the REST API answers it in. */
    public const string HEADER = 'X-Pollora-Diagnostics';

    /** The marker an HTML page carries its report in, as a comment after the document. */
    public const string COMMENT_PREFIX = 'pollora:diagnostics';

    private int $laravelQueries = 0;

    private ?float $bootMs = null;

    /**
     * Whether this request asked for a report and may get one.
     *
     * @param  bool  $debug  WP_DEBUG: a production site answers no
     * @param  array<string, mixed>  $server  The request's `$_SERVER`
     */
    public static function isRequested(bool $debug, array $server): bool
    {
        return $debug && ($server['HTTP_X_POLLORA_DIAGNOSTICS'] ?? null) === '1';
    }

    /**
     * Count one query run by Laravel's database layer.
     *
     * Hooked on Laravel's `QueryExecuted` event, which is dispatched once per
     * statement and opens no connection by listening.
     */
    public function recordLaravelQuery(): void
    {
        $this->laravelQueries++;
    }

    /**
     * Note how long the boot took: from the start of the request to the moment
     * WordPress and Pollora were fully loaded (`wp_loaded`).
     *
     * @param  float  $now  `microtime(true)` at that moment
     * @param  float  $start  The request's start, `$_SERVER['REQUEST_TIME_FLOAT']`
     */
    public function recordBoot(float $now, float $start): void
    {
        $this->bootMs = $this->milliseconds($now - $start);
    }

    /**
     * The report.
     *
     * @param  int  $wordpressQueries  `$wpdb->num_queries`
     * @param  float  $now  `microtime(true)` when the report is made
     * @param  float  $start  The request's start
     * @return array{wordpress_queries: int, laravel_queries: int, boot_ms: float|null, elapsed_ms: float}
     */
    public function report(int $wordpressQueries, float $now, float $start): array
    {
        return [
            'wordpress_queries' => $wordpressQueries,
            'laravel_queries' => $this->laravelQueries,
            'boot_ms' => $this->bootMs,
            'elapsed_ms' => $this->milliseconds($now - $start),
        ];
    }

    /**
     * The report as the value of the response header.
     *
     * @param  array<string, mixed>  $report
     */
    public static function headerValue(array $report): string
    {
        return json_encode($report, JSON_THROW_ON_ERROR);
    }

    /**
     * The report as an HTML comment, to append after the document.
     *
     * `--` and angle brackets cannot occur in the numbers, but the comment is
     * kept closed by construction: JSON escapes `<`, `>` and `&`.
     *
     * @param  array<string, mixed>  $report
     */
    public static function htmlComment(array $report): string
    {
        $json = json_encode($report, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP);

        return sprintf("\n<!-- %s %s -->\n", self::COMMENT_PREFIX, str_replace('--', '- -', $json));
    }

    /**
     * Whether an HTML comment may be appended to what the request sent.
     *
     * Only an HTML document takes one: a redirect has no body, and JSON, XML
     * (feeds, sitemaps) or plain text would be corrupted by it.
     *
     * @param  list<string>  $sentHeaders  `headers_list()`
     * @param  int|false  $status  `http_response_code()`
     */
    public static function acceptsHtmlComment(array $sentHeaders, int|false $status): bool
    {
        if ($status !== false && $status >= 300 && $status < 400) {
            return false;
        }

        foreach ($sentHeaders as $header) {
            if (stripos($header, 'content-type:') === 0) {
                return stripos($header, 'text/html') !== false;
            }
        }

        // PHP sends text/html when nothing says otherwise.
        return true;
    }

    private function milliseconds(float $seconds): float
    {
        return round($seconds * 1000, 1);
    }
}
