<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Closure;
use Composer\InstalledVersions;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;

/**
 * An active WooCommerce has its Pollora integration installed.
 *
 * The framework used to ship that integration. It lives in pollora/woocommerce now,
 * and a project that updated the framework without the package loses, without an error,
 * the Blade templates of the shop, the Route::wp() conditions, the Coming Soon page and
 * the WooCommerce events. This check is the only place the core names the plugin.
 */
final readonly class WooCommercePackageCheck implements CheckInterface
{
    private const string PACKAGE = 'pollora/woocommerce';

    /**
     * @param  (Closure(): bool)|null  $packageInstalled  Tells whether the package is installed; Composer answers by default
     */
    public function __construct(private ?Closure $packageInstalled = null) {}

    public function id(): string
    {
        return 'woocommerce-package';
    }

    public function label(): string
    {
        return 'WooCommerce integration';
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

        if (! class_exists('WooCommerce', false)) {
            return CheckResult::skipped('WooCommerce is not active.');
        }

        $installed = $this->packageInstalled instanceof Closure
            ? ($this->packageInstalled)()
            : InstalledVersions::isInstalled(self::PACKAGE);

        if ($installed) {
            return CheckResult::ok(self::PACKAGE.' is installed.');
        }

        return CheckResult::warning(
            'WooCommerce is active, but its Pollora integration is not installed.',
            [
                'The framework no longer ships it: without '.self::PACKAGE.', the shop has no Blade templates, no Route::wp() conditions (shop, product, cart…), no Coming Soon page and no WooCommerce events.',
            ],
            'composer require '.self::PACKAGE,
        );
    }
}
