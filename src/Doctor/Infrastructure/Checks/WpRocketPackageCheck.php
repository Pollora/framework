<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Closure;
use Composer\InstalledVersions;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;

/**
 * An active WP Rocket has its Pollora integration installed.
 *
 * The framework used to keep WP Rocket from writing `.htaccess` and from setting `WP_CACHE`.
 * That lives in pollora/wp-rocket now, and a project that updated the framework without the
 * package gets, without an error, a WP Rocket that does both on its own.
 */
final readonly class WpRocketPackageCheck implements CheckInterface
{
    private const string PACKAGE = 'pollora/wp-rocket';

    /**
     * @param  (Closure(): bool)|null  $packageInstalled  Tells whether the package is installed; Composer answers by default
     */
    public function __construct(private ?Closure $packageInstalled = null) {}

    public function id(): string
    {
        return 'wp-rocket-package';
    }

    public function label(): string
    {
        return 'WP Rocket integration';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        if (! function_exists('did_action') || \did_action('plugins_loaded') === 0) {
            return CheckResult::skipped('WordPress is not loaded.');
        }

        if (! defined('WP_ROCKET_VERSION')) {
            return CheckResult::skipped('WP Rocket is not active.');
        }

        $installed = $this->packageInstalled instanceof Closure
            ? ($this->packageInstalled)()
            : InstalledVersions::isInstalled(self::PACKAGE);

        if ($installed) {
            return CheckResult::ok(self::PACKAGE.' is installed.');
        }

        return CheckResult::warning(
            'WP Rocket is active, but its Pollora integration is not installed.',
            [
                'The framework no longer ships it: without '.self::PACKAGE.', WP Rocket writes its rules into .htaccess and sets WP_CACHE on its own.',
            ],
            'composer require '.self::PACKAGE,
        );
    }
}
