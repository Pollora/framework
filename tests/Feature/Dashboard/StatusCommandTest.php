<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Nwidart\Modules\Module;
use Pollora\Attributes\PostType;
use Pollora\Attributes\Taxonomy;
use Pollora\Dashboard\Domain\Contracts\WordPressEnvironmentInterface;
use Pollora\Dashboard\Domain\Services\SystemInfoCollector;
use Pollora\Dashboard\UI\Console\StatusCommand;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Modules\Application\Services\ModuleStates;
use Pollora\Modules\Application\Services\ModuleVersions;
use Pollora\Modules\Domain\Contracts\ModuleStateConnector;
use Pollora\VersionCheck\Domain\Contracts\VersionCheckerInterface;
use Pollora\VersionCheck\Domain\Services\VersionComparator;
use Psr\Container\ContainerInterface;

#[PostType('press-summary', singular: 'Press summary', plural: 'Press summaries')]
class CovStatusPressSummary {}

#[Taxonomy('press-topic', singular: 'Press topic', plural: 'Press topics')]
class CovStatusPressTopic {}

/**
 * Bind a collector fed by controlled inputs and register the command.
 *
 * @param  array<string, list<array<string, string>>>  $discovered
 * @param  array<string, mixed>  $stats
 */
function bindStatusCollector(Application $app, string $current, string $latest, array $discovered = [], array $stats = [], ?array $modules = null): void
{
    $checker = Mockery::mock(VersionCheckerInterface::class, [
        'getCurrentVersion' => $current,
        'getLatestVersion' => $latest,
    ]);

    $manager = Mockery::mock(DiscoveryManager::class);
    $manager->shouldReceive('getDiscoveredItems')->andReturnUsing(fn (string $id): array => $discovered[$id] ?? []);
    $manager->shouldReceive('getPerformanceStats')->andReturn($stats);
    $manager->shouldReceive('getEngine')->andThrow(new RuntimeException('no engine'));

    $container = Mockery::mock(ContainerInterface::class);
    $container->shouldReceive('get')->with('translator')->andReturn(new class
    {
        public function getLocale(): string
        {
            return 'fr';
        }
    });

    if ($modules === null) {
        $container->shouldReceive('get')->with('modules')->andThrow(new RuntimeException('none'));
    } else {
        $container->shouldReceive('get')->with('modules')->andReturn($modules['repo']);
        $container->shouldReceive('get')->with(ModuleVersions::class)->andReturn($modules['versions']);
        $container->shouldReceive('get')->with(ModuleStates::class)->andReturn($modules['states']);
    }

    $wordpress = Mockery::mock(WordPressEnvironmentInterface::class);
    $wordpress->shouldReceive('locale')->andReturn('fr_FR');
    $wordpress->shouldReceive('version')->andReturn('7.1.3');
    $wordpress->shouldReceive('postTypeLabel')->andReturn(null);
    $wordpress->shouldReceive('taxonomyLabel')->andReturn(null);
    $wordpress->shouldReceive('isMultisite')->andReturn(true);
    $wordpress->shouldReceive('permalinkStructure')->andReturn('/%postname%/');
    $wordpress->shouldReceive('activeTheme')->andReturn(['name' => 'Nectar', 'version' => '1.7.0', 'template' => 'nectar']);

    $app->instance(
        SystemInfoCollector::class,
        new SystemInfoCollector(new VersionComparator($checker), $manager, $container, '13.40.0', $wordpress)
    );
    $app->make(Kernel::class)->registerCommand($app->make(StatusCommand::class));
}

describe('pollora:status', function (): void {
    it('prints the whole collected information as JSON with --json', function (): void {
        bindStatusCollector($this->app, '13.40.0', '13.40.0');

        $this->artisan('pollora:status', ['--json' => true])
            ->expectsOutputToContain('"wordpress": "7.1.3"')
            ->doesntExpectOutputToContain('Pollora v')
            ->assertSuccessful();
    });

    it('confirms an up-to-date stable release', function (): void {
        bindStatusCollector($this->app, '13.40.0', '13.40.0');

        $this->artisan('pollora:status')
            ->expectsOutputToContain('Pollora v13.40.0 (latest: v13.40.0)')
            ->expectsOutputToContain('PHP '.PHP_VERSION.' | Laravel 13.40.0 | WordPress 7.1.3')
            ->expectsOutputToContain('| Multisite: yes | Permalinks: /%postname%/')
            ->expectsOutputToContain('Theme: Nectar v1.7.0 (nectar)')
            ->expectsOutputToContain('Locales: WordPress fr_FR | Laravel fr')
            ->assertSuccessful();
    });

    it('warns when a newer release exists', function (): void {
        bindStatusCollector($this->app, '13.39.0', '13.40.0');

        $this->artisan('pollora:status')
            ->expectsOutputToContain('Pollora v13.39.0 (latest: v13.40.0) ↑')
            ->assertSuccessful();
    });

    it('shows a development build with the latest stable version', function (): void {
        bindStatusCollector($this->app, 'dev-develop', '13.40.0');

        $this->artisan('pollora:status')
            ->expectsOutputToContain('Pollora dev-develop (latest stable: v13.40.0)')
            ->assertSuccessful();
    });

    it('lists every discovered item with its counts', function (): void {
        bindStatusCollector($this->app, '13.40.0', '13.40.0', [
            'post_types' => [['class' => CovStatusPressSummary::class]],
            'taxonomies' => [['class' => CovStatusPressTopic::class]],
            'hooks' => [['type' => 'action'], ['type' => 'action'], ['type' => 'filter']],
            'wp_rest_routes' => [['class' => 'App\\Rest\\Posts']],
            'wp_cli_commands' => [['class' => 'App\\Cli\\Sync']],
            'schedules' => [['class' => 'App\\Jobs\\Nightly', 'method' => 'run']],
            'service_providers' => [['class' => 'App\\Providers\\Foo']],
        ], ['context' => ['cache_hits' => 4, 'cache_misses' => 1, 'classes_processed' => 12]]);

        $this->artisan('pollora:status')
            ->expectsOutputToContain('Post Types: 1 registered (via discovery)')
            ->expectsOutputToContain('· Press summaries [press-summary] — '.CovStatusPressSummary::class)
            ->expectsOutputToContain('Taxonomies: 1 registered')
            ->expectsOutputToContain('· Press topics [press-topic] — '.CovStatusPressTopic::class)
            ->expectsOutputToContain('Hooks: 3 registered (2 actions, 1 filters)')
            ->expectsOutputToContain('REST API routes: 1 registered')
            ->expectsOutputToContain('· App\\Rest\\Posts')
            ->expectsOutputToContain('WP-CLI commands: 1 registered')
            ->expectsOutputToContain('Scheduled tasks: 1 registered')
            ->expectsOutputToContain('· App\\Jobs\\Nightly::run()')
            ->expectsOutputToContain('Auto-discovered providers: 1')
            ->expectsOutputToContain('Discovery stats: 4 cache hits, 1 misses, 12 classes')
            ->expectsOutputToContain('Discovery cache: disabled (Unknown)')
            ->assertSuccessful();
    });

    it('lists modules with their state and an available update', function (): void {
        $enabled = Mockery::mock(Module::class);
        $enabled->shouldReceive('getName')->andReturn('Shop');
        $enabled->shouldReceive('getDescription')->andReturn('Shop module');
        $enabled->shouldReceive('get')->with('priority', '')->andReturn('0');
        $enabled->shouldReceive('isEnabled')->andReturn(true);

        $disabled = Mockery::mock(Module::class);
        $disabled->shouldReceive('getName')->andReturn('Blog');
        $disabled->shouldReceive('getDescription')->andReturn('Blog module');
        $disabled->shouldReceive('get')->with('priority', '')->andReturn('1');
        $disabled->shouldReceive('isEnabled')->andReturn(false);

        $repo = Mockery::mock();
        $repo->shouldReceive('all')->andReturn(['Shop' => $enabled, 'Blog' => $disabled]);
        $repo->shouldReceive('allEnabled')->andReturn(['shop' => $enabled]);
        $repo->shouldReceive('allDisabled')->andReturn(['blog' => $disabled]);

        $versions = Mockery::mock(ModuleVersions::class);
        $versions->shouldReceive('all')->andReturn(['Shop' => ['version' => '1.0.0', 'latest' => '1.2.0']]);

        $connector = Mockery::mock(ModuleStateConnector::class);
        $connector->shouldReceive('label')->andReturn('JSON file');
        $states = Mockery::mock(ModuleStates::class);
        $states->shouldReceive('connector')->andReturn($connector);

        bindStatusCollector($this->app, '13.40.0', '13.40.0', modules: ['repo' => $repo, 'versions' => $versions, 'states' => $states]);

        $this->artisan('pollora:status')
            ->expectsOutputToContain('Modules: 2 total (1 enabled, 1 disabled)')
            ->expectsOutputToContain('Stored in: JSON file')
            ->expectsOutputToContain('✓ Shop 1.0.0 (1.2.0 available)')
            ->expectsOutputToContain('✗ Blog')
            ->assertSuccessful();
    });
});
