<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Composer\InstalledVersions;
use Pollora\Doctor\Domain\Enums\CheckStatus;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Doctor\Infrastructure\Checks\ObjectCachePackageCheck;

/**
 * A content directory with the given object-cache.php, or none when $dropIn is null.
 */
function objectCacheContentDir(?string $dropIn): string
{
    $dir = sys_get_temp_dir().'/doctor-oc-'.bin2hex(random_bytes(4));
    mkdir($dir);

    if ($dropIn !== null) {
        file_put_contents($dir.'/object-cache.php', $dropIn);
    }

    return $dir;
}

function objectCacheRemove(string $dir): void
{
    if (is_file($dir.'/object-cache.php')) {
        unlink($dir.'/object-cache.php');
    }

    rmdir($dir);
}

function objectCacheRun(bool $installed, bool $redis, ?string $dropIn): CheckResult
{
    $dir = objectCacheContentDir($dropIn);

    try {
        return (new ObjectCachePackageCheck(fn (): bool => $installed, fn (): bool => $redis, $dir))->run(RunContext::Console);
    } finally {
        objectCacheRemove($dir);
    }
}

describe('persistent object cache check', function (): void {
    beforeEach(function (): void {
        Functions\when('did_action')->justReturn(1);
    });

    test('has an id, a label and runs in the console only', function (): void {
        $check = new ObjectCachePackageCheck;

        expect($check->id())->toBe('object-cache-package')
            ->and($check->label())->toBe('Persistent object cache')
            ->and($check->runsIn())->toBe([RunContext::Console]);
    });

    test('is skipped when WordPress is not loaded', function (): void {
        Functions\when('did_action')->justReturn(0);

        $result = (new ObjectCachePackageCheck(fn (): bool => false, fn (): bool => true, sys_get_temp_dir()))->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Skipped)
            ->and($result->summary)->toBe('WordPress is not loaded.');
    });

    test('suggests the package when phpredis is loaded and nothing persists the cache', function (): void {
        $result = objectCacheRun(false, true, null);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and($result->summary)->toBe('phpredis is available, but the object cache is not persisted.')
            ->and($result->details)->toBe(['Without a drop-in, every request rebuilds what WordPress caches (options, terms, queries).'])
            ->and($result->fix)->toBe('composer require pollora/object-cache && php artisan pollora:object-cache:enable');
    });

    test('stays quiet without phpredis', function (): void {
        $result = objectCacheRun(false, false, null);

        expect($result->status)->toBe(CheckStatus::Skipped)
            ->and($result->summary)->toBe('phpredis is not loaded: the object cache stays in memory for each request.');
    });

    test('leaves another object cache alone', function (): void {
        $result = objectCacheRun(false, true, "<?php\n// Redis Object Cache");

        expect($result->status)->toBe(CheckStatus::Skipped)
            ->and($result->summary)->toBe('Another object cache drop-in is in place.');
    });

    test('says to enable the drop-in when the package has none', function (): void {
        $result = objectCacheRun(true, true, null);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and($result->summary)->toBe('pollora/object-cache is installed, but its drop-in is not.')
            ->and($result->details)->toBe(['WordPress keeps its object cache in memory until object-cache.php is in the content directory.'])
            ->and($result->fix)->toBe('php artisan pollora:object-cache:enable');
    });

    test('warns when the drop-in is another one, and names the forced install', function (): void {
        $result = objectCacheRun(true, true, "<?php\n// something else");

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and($result->summary)->toBe('object-cache.php belongs to another object cache, not to pollora/object-cache.')
            ->and($result->details)->toBe(['The package never replaces a drop-in it did not write.'])
            ->and($result->fix)->toBe('php artisan pollora:object-cache:enable --force');
    });

    test('is ok when the package owns the drop-in', function (): void {
        $result = objectCacheRun(true, true, "<?php\n// Pollora-Object-Cache-Drop-In: 1");

        expect($result->status)->toBe(CheckStatus::Ok)
            ->and($result->summary)->toBe('pollora/object-cache is installed and its drop-in is in place.');
    });

    test('reads the drop-in through a trailing slash on the content directory', function (): void {
        $dir = objectCacheContentDir("<?php\n// Pollora-Object-Cache-Drop-In: 1");

        $result = (new ObjectCachePackageCheck(fn (): bool => true, fn (): bool => true, $dir.'/'))->run(RunContext::Console);
        objectCacheRemove($dir);

        expect($result->status)->toBe(CheckStatus::Ok);
    });

    test('finds no drop-in without a content directory', function (): void {
        $result = (new ObjectCachePackageCheck(fn (): bool => true, fn (): bool => true, ''))->run(RunContext::Console);

        expect($result->status)->toBe(CheckStatus::Warning)
            ->and($result->summary)->toContain('its drop-in is not');
    });

    test('asks Composer whether the package is installed, and the extension list about phpredis, by default', function (): void {
        $dir = objectCacheContentDir(null);
        $result = (new ObjectCachePackageCheck(null, null, $dir))->run(RunContext::Console);
        objectCacheRemove($dir);

        $installed = InstalledVersions::isInstalled('pollora/object-cache');

        if ($installed) {
            expect($result->summary)->toContain('is installed, but its drop-in is not');

            return;
        }

        expect($result->status)->toBe(extension_loaded('redis') ? CheckStatus::Warning : CheckStatus::Skipped);
    });

    test('takes WP_CONTENT_DIR as the content directory by default', function (): void {
        $result = (new ObjectCachePackageCheck(fn (): bool => true, fn (): bool => true))->run(RunContext::Console);

        expect($result->status)->toBeIn([CheckStatus::Ok, CheckStatus::Warning]);
    });
});
