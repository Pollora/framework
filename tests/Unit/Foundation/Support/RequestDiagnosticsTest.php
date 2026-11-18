<?php

declare(strict_types=1);

use Pollora\Foundation\Support\RequestDiagnostics;

/**
 * The report is the input of the performance gates: what it says, and when it
 * stays silent, is the contract. Silence matters most, since production must
 * never hand it out.
 */
describe('RequestDiagnostics::isRequested()', function (): void {
    it('answers only when WP_DEBUG is on and the request sends the header as 1', function (bool $debug, array $server, bool $expected): void {
        expect(RequestDiagnostics::isRequested($debug, $server))->toBe($expected);
    })->with([
        'debug and header' => [true, ['HTTP_X_POLLORA_DIAGNOSTICS' => '1'], true],
        'no debug' => [false, ['HTTP_X_POLLORA_DIAGNOSTICS' => '1'], false],
        'no header' => [true, [], false],
        'header not 1' => [true, ['HTTP_X_POLLORA_DIAGNOSTICS' => 'true'], false],
        'header empty' => [true, ['HTTP_X_POLLORA_DIAGNOSTICS' => ''], false],
    ]);
});

describe('RequestDiagnostics::report()', function (): void {
    it('counts the Laravel queries it is told about and rounds milliseconds to a tenth', function (): void {
        $diagnostics = new RequestDiagnostics;
        $diagnostics->recordLaravelQuery();
        $diagnostics->recordLaravelQuery();
        $diagnostics->recordBoot(100.0449, 100.0);

        expect($diagnostics->report(31, 100.5, 100.0))->toBe([
            'wordpress_queries' => 31,
            'laravel_queries' => 2,
            'boot_ms' => 44.9,
            'elapsed_ms' => 500.0,
        ]);
    });

    it('reports no boot time until the boot was noted, rather than a made-up zero', function (): void {
        expect((new RequestDiagnostics)->report(0, 10.0, 10.0)['boot_ms'])->toBeNull();
    });
});

describe('RequestDiagnostics output', function (): void {
    it('writes the header value as JSON a client can parse back', function (): void {
        $report = ['wordpress_queries' => 12, 'laravel_queries' => 0, 'boot_ms' => null, 'elapsed_ms' => 8.5];

        expect(json_decode(RequestDiagnostics::headerValue($report), true))->toBe($report);
    });

    it('writes an HTML comment that stays one comment whatever the report holds', function (): void {
        $comment = RequestDiagnostics::htmlComment(['note' => '--> <script>alert(1)</script> & --']);
        $inner = trim(substr($comment, strlen("\n<!-- "), -strlen(" -->\n")));

        expect($comment)->toStartWith("\n<!-- pollora:diagnostics {")
            ->and($comment)->toEndWith("} -->\n")
            ->and($inner)->not->toContain('-->')
            ->and($inner)->not->toContain('<')
            ->and($inner)->not->toContain('--');
    });
});

describe('RequestDiagnostics::acceptsHtmlComment()', function (): void {
    it('appends to HTML, and to a page that set no content type', function (array $headers, int|false $status, bool $expected): void {
        expect(RequestDiagnostics::acceptsHtmlComment($headers, $status))->toBe($expected);
    })->with([
        'html' => [['Content-Type: text/html; charset=UTF-8'], 200, true],
        'html in another case' => [['content-type: TEXT/HTML'], 404, true],
        'no content type' => [['X-Powered-By: PHP'], 200, true],
        'no status known' => [[], false, true],
        'json' => [['Content-Type: application/json'], 200, false],
        'xml feed' => [['Content-Type: application/rss+xml; charset=UTF-8'], 200, false],
        'plain text' => [['Content-Type: text/plain'], 200, false],
        'redirect' => [['Content-Type: text/html'], 301, false],
    ]);
});
