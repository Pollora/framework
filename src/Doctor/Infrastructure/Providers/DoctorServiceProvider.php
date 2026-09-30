<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Doctor\Application\Services\Doctor;
use Pollora\Doctor\Infrastructure\Checks\BlockRegistrationCheck;
use Pollora\Doctor\Infrastructure\Checks\BlockThemeRoutesCheck;
use Pollora\Doctor\Infrastructure\Checks\DiscoveryCacheCheck;
use Pollora\Doctor\Infrastructure\Checks\EnvironmentFileCheck;
use Pollora\Doctor\Infrastructure\Checks\PatchesLockCheck;
use Pollora\Doctor\Infrastructure\Checks\PatternCacheCheck;
use Pollora\Doctor\Infrastructure\Checks\PatternFilesCheck;
use Pollora\Doctor\Infrastructure\Checks\ThemeBuildCheck;
use Pollora\Doctor\Infrastructure\Checks\ThemeDirectoryCheck;
use Pollora\Doctor\Infrastructure\Checks\ThemePlaceholdersCheck;
use Pollora\Doctor\Infrastructure\Checks\WordPressCorePatchCheck;
use Pollora\Doctor\UI\Console\DoctorCommand;
use Pollora\Doctor\UI\Http\SiteHealthTests;
use Pollora\Hook\Domain\Contract\Filter;

/**
 * pollora:doctor and its Site Health tests: one list of checks, two places to read them.
 */
class DoctorServiceProvider extends ServiceProvider
{
    public const string CHECKS_TAG = 'pollora.doctor.checks';

    /**
     * In the order a developer should read them: the install, then the theme.
     *
     * @var list<class-string>
     */
    private const array CHECKS = [
        WordPressCorePatchCheck::class,
        PatchesLockCheck::class,
        EnvironmentFileCheck::class,
        DiscoveryCacheCheck::class,
        ThemeBuildCheck::class,
        ThemeDirectoryCheck::class,
        ThemePlaceholdersCheck::class,
        PatternFilesCheck::class,
        PatternCacheCheck::class,
        BlockThemeRoutesCheck::class,
        BlockRegistrationCheck::class,
    ];

    public function register(): void
    {
        $this->app->tag(self::CHECKS, self::CHECKS_TAG);
        $this->app->singleton(Doctor::class, fn ($app): Doctor => new Doctor($app->tagged(self::CHECKS_TAG)));

        if ($this->app->runningInConsole()) {
            $this->commands([DoctorCommand::class]);
        }
    }

    public function boot(Filter $filter): void
    {
        $filter->add('site_status_tests', fn (array $tests): array => $this->app->make(SiteHealthTests::class)->register($tests));
    }
}
