<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Infrastructure\Checks\ApiSecurityPluginsCheck;

/**
 * The check with the given active plugins, and the given `wordpress.api_plugins`.
 *
 * @param  list<string>  $active
 */
function apiCheck(array $active, mixed $allowed = []): ApiSecurityPluginsCheck
{
    config(['wordpress.api_plugins' => $allowed]);

    return new ApiSecurityPluginsCheck(fn (): array => $active);
}

describe('ApiSecurityPluginsCheck', function (): void {
    it('warns when a firewall is active and no plugin loads on /api/', function (): void {
        $result = apiCheck(['wordfence/wordfence.php', 'akismet/akismet.php'])->run(RunContext::Console);

        expect($result->status->value)->toBe('warning')
            ->and($result->summary)->toContain('does not load on /api/')
            ->and($result->details[0])->toBe('Not loaded there: wordfence (firewall)')
            ->and($result->fix)->toContain("['wordfence']");
    });

    it('lists every known plugin left out, sorted, with what it does', function (): void {
        $result = apiCheck(['jwt-auth/jwt-auth.php', 'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php', 'ninjafirewall/ninjafirewall.php'])
            ->run(RunContext::Http);

        expect($result->details[0])->toBe('Not loaded there: jwt-auth (JWT authentication), limit-login-attempts-reloaded (rate limiter), ninjafirewall (firewall)');
    });

    it('recognises a single-file plugin by its file name', function (): void {
        $result = apiCheck(['jwt-auth.php'])->run(RunContext::Console);

        expect($result->status->value)->toBe('warning');
    });

    it('passes when no active plugin is a known security plugin', function (): void {
        $result = apiCheck(['akismet/akismet.php', 'wordfence-lookalike/x.php'])->run(RunContext::Console);

        expect($result->status->value)->toBe('ok');
    });

    it('passes when there is no active plugin', function (): void {
        expect(apiCheck([])->run(RunContext::Console)->status->value)->toBe('ok');
    });

    it('passes when the plugin is listed in api_plugins, by directory, by file or by glob', function (array $allowed): void {
        expect(apiCheck(['wordfence/wordfence.php'], $allowed)->run(RunContext::Console)->status->value)->toBe('ok');
    })->with([
        'directory' => [['wordfence']],
        'file' => [['wordfence/wordfence.php']],
        'glob' => [['word*']],
    ]);

    it('still warns about the plugins an incomplete api_plugins list leaves out', function (): void {
        $result = apiCheck(['wordfence/wordfence.php', 'jwt-auth/jwt-auth.php'], ['jwt-auth'])->run(RunContext::Console);

        expect($result->status->value)->toBe('warning')
            ->and($result->details[0])->toBe('Not loaded there: wordfence (firewall)');
    });

    it('does not read a glob into a name without a star', function (): void {
        expect(apiCheck(['wordfence/wordfence.php'], ['word?ence'])->run(RunContext::Console)->status->value)->toBe('warning');
    });

    it('is skipped when the lightweight mode is off', function (mixed $allowed): void {
        $result = apiCheck(['wordfence/wordfence.php'], $allowed)->run(RunContext::Console);

        expect($result->status->value)->toBe('skipped')
            ->and($result->summary)->toContain('lightweight mode');
    })->with([
        'null' => [null],
        'star' => [['*']],
        'star among others' => [['woocommerce', '*']],
    ]);

    it('is skipped when WordPress is not loaded', function (): void {
        config(['wordpress.api_plugins' => []]);

        expect((new ApiSecurityPluginsCheck)->run(RunContext::Console)->status->value)->toBe('skipped');
    });

    it('reads the active_plugins option once WordPress has loaded its plugins', function (): void {
        config(['wordpress.api_plugins' => []]);
        Functions\when('did_action')->justReturn(1);
        Functions\when('get_option')->alias(fn (string $name, mixed $default = false): array => $name === 'active_plugins'
            ? ['wordfence/wordfence.php', 42, 'akismet/akismet.php']
            : []);

        $result = (new ApiSecurityPluginsCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('warning')
            ->and($result->details[0])->toBe('Not loaded there: wordfence (firewall)');
    });

    it('is skipped before WordPress has loaded its plugins', function (): void {
        config(['wordpress.api_plugins' => []]);
        Functions\when('did_action')->justReturn(0);
        Functions\when('get_option')->justReturn(['wordfence/wordfence.php']);

        expect((new ApiSecurityPluginsCheck)->run(RunContext::Console)->status->value)->toBe('skipped');
    });

    it('is named, and runs in the console and in HTTP', function (): void {
        $check = new ApiSecurityPluginsCheck;

        expect($check->id())->toBe('api-security-plugins')
            ->and($check->label())->toBe('Security plugins on /api/ routes')
            ->and($check->runsIn())->toBe([RunContext::Console, RunContext::Http]);
    });
});
