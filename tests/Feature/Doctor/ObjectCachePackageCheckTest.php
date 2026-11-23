<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Doctor\Domain\Enums\CheckStatus;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Infrastructure\Checks\ObjectCachePackageCheck;

function objectCacheCheck(bool $installed, bool $redis, ?string $dropIn): ObjectCachePackageCheck
{
    return new ObjectCachePackageCheck(fn (): bool => $installed, fn (): bool => $redis, fn (): ?string => $dropIn);
}

describe('persistent object cache check', function (): void {
    test('is skipped when WordPress is not loaded', function (): void {
        Functions\when('did_action')->justReturn(0);

        expect(objectCacheCheck(false, true, null)->run(RunContext::Console)->status)->toBe(CheckStatus::Skipped);
    });

    test('suggests the package when phpredis is loaded and nothing persists the cache', function (): void {
        Functions\when('did_action')->justReturn(1);

        $result = objectCacheCheck(false, true, null)->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and($result->fix)->toContain('composer require pollora/object-cache')
            ->and($result->fix)->toContain('pollora:object-cache:enable');
    });

    test('stays quiet without phpredis', function (): void {
        Functions\when('did_action')->justReturn(1);

        $result = objectCacheCheck(false, false, null)->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Skipped)
            ->and($result->summary)->toContain('phpredis');
    });

    test('leaves another object cache alone', function (): void {
        Functions\when('did_action')->justReturn(1);

        $result = objectCacheCheck(false, true, "<?php\n// Redis Object Cache")->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Skipped)
            ->and($result->summary)->toContain('Another object cache');
    });

    test('says to enable the drop-in when the package has none', function (): void {
        Functions\when('did_action')->justReturn(1);

        $result = objectCacheCheck(true, true, null)->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and($result->fix)->toBe('php artisan pollora:object-cache:enable');
    });

    test('warns when the drop-in is another one, and names the forced install', function (): void {
        Functions\when('did_action')->justReturn(1);

        $result = objectCacheCheck(true, true, "<?php\n// something else")->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and($result->fix)->toBe('php artisan pollora:object-cache:enable --force');
    });

    test('is ok when the package owns the drop-in', function (): void {
        Functions\when('did_action')->justReturn(1);

        $result = objectCacheCheck(true, true, "<?php\n// Pollora-Object-Cache-Drop-In: 1")->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Ok);
    });

    test('runs in the console only', function (): void {
        expect((new ObjectCachePackageCheck)->runsIn())->toBe([RunContext::Console])
            ->and((new ObjectCachePackageCheck)->id())->toBe('object-cache-package');
    });

    test('reads the drop-in from the content directory by default', function (): void {
        Functions\when('did_action')->justReturn(1);
        $dir = sys_get_temp_dir().'/doctor-oc-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/object-cache.php', "<?php\n// Pollora-Object-Cache-Drop-In: 1");

        if (! defined('WP_CONTENT_DIR')) {
            define('WP_CONTENT_DIR', $dir);
        }

        $check = new ObjectCachePackageCheck(fn (): bool => true, fn (): bool => true);
        $status = $check->run(RunContext::Console)->status;

        unlink($dir.'/object-cache.php');
        rmdir($dir);

        expect($status)->toBeIn([CheckStatus::Ok, CheckStatus::Warning]);
    });
});
