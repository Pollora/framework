<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Closure;
use Composer\InstalledVersions;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;

/**
 * A persistent object cache is in place when Redis is available.
 *
 * WordPress keeps its object cache in memory for one request unless an
 * `object-cache.php` drop-in stores it elsewhere. pollora/object-cache is the
 * Redis one for Pollora; this check suggests it when phpredis is loaded and
 * nothing else persists the cache, and says when the package is installed but
 * its drop-in is not.
 */
final readonly class ObjectCachePackageCheck implements CheckInterface
{
    private const string PACKAGE = 'pollora/object-cache';

    /** The line the package writes at the top of the drop-in it owns. */
    private const string MARKER = 'Pollora-Object-Cache-Drop-In:';

    /**
     * @param  (Closure(): bool)|null  $packageInstalled  Tells whether the package is installed; Composer answers by default
     * @param  (Closure(): bool)|null  $redisLoaded  Tells whether phpredis is loaded; the extension list answers by default
     * @param  (Closure(): ?string)|null  $dropIn  Returns the contents of the drop-in, or null when there is none; WP_CONTENT_DIR answers by default
     */
    public function __construct(
        private ?Closure $packageInstalled = null,
        private ?Closure $redisLoaded = null,
        private ?Closure $dropIn = null,
    ) {}

    public function id(): string
    {
        return 'object-cache-package';
    }

    public function label(): string
    {
        return 'Persistent object cache';
    }

    public function runsIn(): array
    {
        return [RunContext::Console];
    }

    public function run(RunContext $context): CheckResult
    {
        if (! function_exists('did_action') || \did_action('plugins_loaded') === 0) {
            return CheckResult::skipped('WordPress is not loaded.');
        }

        $contents = $this->dropInContents();
        $installed = $this->packageInstalled instanceof Closure
            ? ($this->packageInstalled)()
            : InstalledVersions::isInstalled(self::PACKAGE);

        if ($installed) {
            return $this->installed($contents);
        }

        if ($contents !== null) {
            return CheckResult::skipped('Another object cache drop-in is in place.');
        }

        $redis = $this->redisLoaded instanceof Closure ? ($this->redisLoaded)() : extension_loaded('redis');

        if (! $redis) {
            return CheckResult::skipped('phpredis is not loaded: the object cache stays in memory for each request.');
        }

        return CheckResult::warning(
            'phpredis is available, but the object cache is not persisted.',
            ['Without a drop-in, every request rebuilds what WordPress caches (options, terms, queries).'],
            'composer require '.self::PACKAGE.' && php artisan pollora:object-cache:enable',
        );
    }

    /**
     * Judge the drop-in of a project that has the package.
     */
    private function installed(?string $contents): CheckResult
    {
        if ($contents === null) {
            return CheckResult::warning(
                self::PACKAGE.' is installed, but its drop-in is not.',
                ['WordPress keeps its object cache in memory until object-cache.php is in the content directory.'],
                'php artisan pollora:object-cache:enable',
            );
        }

        if (! str_contains($contents, self::MARKER)) {
            return CheckResult::warning(
                'object-cache.php belongs to another object cache, not to '.self::PACKAGE.'.',
                ['The package never replaces a drop-in it did not write.'],
                'php artisan pollora:object-cache:enable --force',
            );
        }

        return CheckResult::ok(self::PACKAGE.' is installed and its drop-in is in place.');
    }

    /**
     * Read the drop-in, or null when the project has none.
     */
    private function dropInContents(): ?string
    {
        if ($this->dropIn instanceof Closure) {
            return ($this->dropIn)();
        }

        if (! defined('WP_CONTENT_DIR')) {
            return null;
        }

        $path = rtrim((string) constant('WP_CONTENT_DIR'), '/').'/object-cache.php';
        $contents = is_file($path) ? file_get_contents($path) : false;

        return $contents === false ? null : $contents;
    }
}
