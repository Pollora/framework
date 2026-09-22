<?php

declare(strict_types=1);

use Pollora\Login\Domain\Models\LoginPalette;
use Pollora\Login\Domain\Models\LoginStylesheet;
use Pollora\Login\Domain\Models\ResolvedLogo;

$rules = <<<'CSS'
body.login.pollora-login { background: var(--pollora-login-background); }
/* pollora:logo-start */
.pollora-login h1 a { background-image: var(--pollora-login-logo); }
/* pollora:logo-end */
.pollora-login form { border-radius: var(--pollora-login-radius); }
CSS;

describe('LoginStylesheet', function () use ($rules): void {
    it('writes the palette out as custom properties', function () use ($rules): void {
        $css = (new LoginStylesheet($rules))->render(
            LoginPalette::fromValues(['primary' => '#ff5334']),
            null
        );

        expect($css)->toContain('--pollora-login-primary: #ff5334;')
            ->and($css)->toStartWith(':root {')
            ->and($css)->toContain('.pollora-login form');
    });

    it('cuts the logo rules out when nothing resolved', function () use ($rules): void {
        // `background-image: var(--x)` with `--x` undeclared is invalid at
        // computed-value time, which would leave the anchor with no background
        // at all — erasing WordPress's own logo rather than replacing it.
        $css = (new LoginStylesheet($rules))->render(LoginPalette::fromValues([]), null);

        expect($css)->not->toContain('--pollora-login-logo')
            ->and($css)->not->toContain('.pollora-login h1 a')
            ->and($css)->toContain('.pollora-login form');
    });

    it('keeps the logo rules, and sizes them, when one resolved', function () use ($rules): void {
        $css = (new LoginStylesheet($rules))->render(
            LoginPalette::fromValues([]),
            new ResolvedLogo('url("https://example.test/logo.svg")', 220, 89)
        );

        expect($css)->toContain('--pollora-login-logo: url("https://example.test/logo.svg");')
            ->and($css)->toContain('--pollora-login-logo-width: 220px;')
            ->and($css)->toContain('--pollora-login-logo-height: 89px;')
            ->and($css)->toContain('.pollora-login h1 a');
    });

    it('writes a data URI through untouched', function () use ($rules): void {
        // The palette's own check rejects semicolons, and `data:image/svg+xml;
        // base64,` has one: routing the logo through it silently dropped the
        // logo the first time this ran on a real site.
        $logo = new ResolvedLogo('url("data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=")', 220, 89);

        expect((new LoginStylesheet($rules))->render(LoginPalette::fromValues([]), $logo))
            ->toContain('data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=');
    });

    it('drops a palette value that could escape the declaration', function (string $value) use ($rules): void {
        // Most values come from a theme.json in a repository, but the `custom`
        // origin is whatever the site editor saved in the database.
        $css = (new LoginStylesheet($rules))->render(
            LoginPalette::fromValues(['primary' => $value]),
            null
        );

        expect($css)->not->toContain('--pollora-login-primary:');
    })->with([
        'closes the rule' => ['red; } body { display: none } .x {'],
        'opens a block' => ['red {'],
        'ends the comment' => ['red */'],
        'pulls in a stylesheet' => ['@import url(https://evil.test/x.css)'],
        'legacy expression' => ['expression(alert(1))'],
        'closes the style element' => ['</style'],
        'blank' => ['   '],
    ]);

    it('survives a stylesheet it could not read', function (): void {
        $css = (new LoginStylesheet(''))->render(LoginPalette::fromValues([]), null);

        expect($css)->toContain('--pollora-login-primary:');
    });
});
