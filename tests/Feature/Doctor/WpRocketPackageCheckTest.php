<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Doctor\Domain\Enums\CheckStatus;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Infrastructure\Checks\WpRocketPackageCheck;

// Declared once, last: a constant stays defined for the rest of the process.
function declareWpRocket(): void
{
    if (! defined('WP_ROCKET_VERSION')) {
        define('WP_ROCKET_VERSION', '3.17');
    }
}

describe('WP Rocket integration check', function (): void {
    test('is skipped when WordPress is not loaded', function (): void {
        Functions\when('did_action')->justReturn(0);

        $result = (new WpRocketPackageCheck)->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Skipped);
    });

    test('is skipped when WP Rocket is not active', function (): void {
        Functions\when('did_action')->justReturn(1);

        $result = (new WpRocketPackageCheck(fn (): bool => false))->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Skipped)
            ->and($result->summary)->toContain('not active');
    });

    test('warns, and says what to install, when the package is missing', function (): void {
        Functions\when('did_action')->justReturn(1);
        declareWpRocket();

        $result = (new WpRocketPackageCheck(fn (): bool => false))->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and($result->fix)->toBe('composer require pollora/wp-rocket');
    });

    test('is ok when the package is installed', function (): void {
        Functions\when('did_action')->justReturn(1);
        declareWpRocket();

        $result = (new WpRocketPackageCheck(fn (): bool => true))->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Ok);
    });

    test('runs in the console and in Site Health', function (): void {
        expect((new WpRocketPackageCheck)->runsIn())->toBe([RunContext::Console, RunContext::Http]);
    });
});
