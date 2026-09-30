<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Doctor\Application\Services\Doctor;
use Pollora\Doctor\Infrastructure\Checks\AssetBuildCheck;
use Pollora\Doctor\Infrastructure\Checks\BlockRegistrationCheck;
use Pollora\Doctor\Infrastructure\Checks\BlockThemeRoutesCheck;
use Pollora\Doctor\Infrastructure\Checks\DevelopmentCachesCheck;
use Pollora\Doctor\Infrastructure\Checks\DiscoveryCacheCheck;
use Pollora\Doctor\Infrastructure\Checks\EnvironmentFileCheck;
use Pollora\Doctor\Infrastructure\Checks\LegacyBlocksDirectoryCheck;
use Pollora\Doctor\Infrastructure\Checks\PatchesLockCheck;
use Pollora\Doctor\Infrastructure\Checks\PatternCacheCheck;
use Pollora\Doctor\Infrastructure\Checks\PatternFilesCheck;
use Pollora\Doctor\Infrastructure\Checks\SymlinkedDirectoryCheck;
use Pollora\Doctor\Infrastructure\Checks\TemplatePlaceholdersCheck;
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
     * In the order a developer should read them: the install, then what the project builds.
     *
     * @var list<class-string>
     */
    private const array CHECKS = [
        WordPressCorePatchCheck::class,
        PatchesLockCheck::class,
        EnvironmentFileCheck::class,
        DevelopmentCachesCheck::class,
        DiscoveryCacheCheck::class,
        AssetBuildCheck::class,
        SymlinkedDirectoryCheck::class,
        TemplatePlaceholdersCheck::class,
        PatternFilesCheck::class,
        PatternCacheCheck::class,
        BlockThemeRoutesCheck::class,
        LegacyBlocksDirectoryCheck::class,
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
