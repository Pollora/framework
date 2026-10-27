<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Doctor\Domain\Enums\CheckStatus;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Infrastructure\Checks\WooCommercePackageCheck;

// Declared once, last: a class stays declared for the rest of the process.
function declareWooCommerce(): void
{
    if (! class_exists('WooCommerce', false)) {
        eval('class WooCommerce {}');
    }
}

describe('WooCommerce integration check', function (): void {
    test('is skipped when WordPress is not loaded', function (): void {
        Functions\when('did_action')->justReturn(0);

        $result = (new WooCommercePackageCheck)->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Skipped);
    });

    test('is skipped when WooCommerce is not active', function (): void {
        Functions\when('did_action')->justReturn(1);

        $result = (new WooCommercePackageCheck(fn (): bool => false))->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Skipped)
            ->and($result->summary)->toContain('not active');
    });

    test('warns, and says what to install, when the package is missing', function (): void {
        Functions\when('did_action')->justReturn(1);
        declareWooCommerce();

        $result = (new WooCommercePackageCheck(fn (): bool => false))->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and($result->fix)->toBe('composer require pollora/woocommerce');
    });

    test('is ok when the package is installed', function (): void {
        Functions\when('did_action')->justReturn(1);
        declareWooCommerce();

        $result = (new WooCommercePackageCheck(fn (): bool => true))->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Ok);
    });

    test('runs in the console and in Site Health', function (): void {
        expect((new WooCommercePackageCheck)->runsIn())->toBe([RunContext::Console, RunContext::Http]);
    });
});
