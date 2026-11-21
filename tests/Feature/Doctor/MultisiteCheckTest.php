<?php

declare(strict_types=1);

use Pollora\Doctor\Domain\Enums\CheckStatus;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Infrastructure\Checks\MultisiteCheck;

describe('Multisite check', function (): void {
    test('is skipped on a single site', function (): void {
        $result = (new MultisiteCheck(fn (): bool => false))->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Skipped)
            ->and($result->summary)->toContain('single site')
            ->and($result->details)->toBe([]);
    });

    test('reads the project declaration by default', function (): void {
        config()->set('wordpress.constants', []);

        expect((new MultisiteCheck)->run(RunContext::Http)->status)->toBe(CheckStatus::Skipped);

        config()->set('wordpress.constants', ['multisite' => true]);

        expect((new MultisiteCheck)->run(RunContext::Http)->status)->toBe(CheckStatus::Warning);
    });

    test('warns about a sub-directory network and lists the limits', function (): void {
        $result = (new MultisiteCheck(fn (): bool => true, fn (): bool => false))->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and($result->summary)->toContain('limited')
            ->and($result->details[0])->toContain('/site/wp-admin/')
            ->and($result->details)->toHaveCount(5)
            ->and(implode("\n", $result->details))->toContain('main site')
            ->and(implode("\n", $result->details))->toContain('discovery cache')
            ->and(implode("\n", $result->details))->toContain('Not verified')
            ->and($result->fix)->not->toBeNull();
    });

    test('does not claim the sub-directory limit for a subdomain network', function (): void {
        $result = (new MultisiteCheck(fn (): bool => true, fn (): bool => true))->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and($result->details)->toHaveCount(4)
            ->and(implode("\n", $result->details))->not->toContain('Sub-directory');
    });

    test('takes a network without SUBDOMAIN_INSTALL for a sub-directory one', function (): void {
        $result = (new MultisiteCheck(fn (): bool => true))->run(RunContext::Console);

        expect($result->details[0])->toContain('Sub-directory network');
    });

    test('runs in the console and in Site Health', function (): void {
        expect((new MultisiteCheck)->runsIn())->toBe([RunContext::Console, RunContext::Http])
            ->and((new MultisiteCheck)->id())->toBe('multisite')
            ->and((new MultisiteCheck)->label())->toBe('Multisite');
    });
});
