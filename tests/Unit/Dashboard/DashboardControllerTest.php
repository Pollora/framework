<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Nwidart\Modules\Module;
use Pollora\Dashboard\Domain\Contracts\WordPressEnvironmentInterface;
use Pollora\Dashboard\Domain\Services\SystemInfoCollector;
use Pollora\Dashboard\UI\Http\DashboardController;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\VersionCheck\Domain\Contracts\VersionCheckerInterface;
use Pollora\VersionCheck\Domain\Services\VersionComparator;
use Psr\Container\ContainerInterface;

/**
 * Render the dashboard page for a collector fed by controlled inputs.
 *
 * @param  array<string, list<array<string, string>>>  $discovered
 * @param  array<string, mixed>  $stats
 */
function renderDashboardPage(string $current, string $latest, array $discovered = [], array $stats = [], ?object $modules = null, bool $debug = false): string
{
    $manager = Mockery::mock(DiscoveryManager::class);
    $manager->shouldReceive('getDiscoveredItems')->andReturnUsing(fn (string $id): array => $discovered[$id] ?? []);
    $manager->shouldReceive('getPerformanceStats')->andReturn($stats);
    $manager->shouldReceive('getEngine')->andThrow(new RuntimeException('no engine'));

    $container = Mockery::mock(ContainerInterface::class);
    $container->shouldReceive('get')->with('translator')->andThrow(new RuntimeException('none'));
    if ($modules === null) {
        $container->shouldReceive('get')->with('modules')->andThrow(new RuntimeException('none'));
    } else {
        $container->shouldReceive('get')->with('modules')->andReturn($modules);
        $container->shouldReceive('get')->andThrow(new RuntimeException('none'));
    }

    $wordpress = Mockery::mock(WordPressEnvironmentInterface::class);
    $wordpress->shouldReceive('locale')->andReturn('fr_FR');
    $wordpress->shouldReceive('version')->andReturn('7.1.3');
    $wordpress->shouldReceive('isMultisite')->andReturn(false);
    $wordpress->shouldReceive('permalinkStructure')->andReturn('');
    $wordpress->shouldReceive('postTypeLabel')->andReturn(null);
    $wordpress->shouldReceive('taxonomyLabel')->andReturn(null);
    $wordpress->shouldReceive('activeTheme')->andReturn(['name' => 'Nectar <b>', 'version' => '1.7.0', 'template' => 'nectar']);

    $checker = Mockery::mock(VersionCheckerInterface::class, [
        'getCurrentVersion' => $current,
        'getLatestVersion' => $latest,
    ]);

    $collector = new SystemInfoCollector(new VersionComparator($checker), $manager, $container, '13.40.0', $wordpress);

    ob_start();
    (new DashboardController($collector))();

    return (string) ob_get_clean();
}

beforeEach(function (): void {
    Functions\when('admin_url')->alias(fn (string $path = ''): string => 'https://example.test/wp-admin/'.$path);
    Functions\when('esc_html')->alias(fn ($t): string => htmlspecialchars((string) $t));
});

describe('DashboardController', function (): void {
    it('shows the environment and the active theme, escaping what WordPress reports', function (): void {
        $html = renderDashboardPage('13.40.0', '13.40.0');

        expect($html)->toContain('<td>PHP</td><td>'.PHP_VERSION.'</td>')
            ->toContain('<td>Laravel</td><td>13.40.0</td>')
            ->toContain('<td>WordPress</td><td>7.1.3</td>')
            ->toContain('Nectar &lt;b&gt;')
            ->not->toContain('Nectar <b>')
            ->toContain('<code>nectar</code>')
            ->toContain('<code>Plain</code>');
    });

    it('shows the stable version without an update arrow when up to date', function (): void {
        $html = renderDashboardPage('13.40.0', '13.40.0');

        expect($html)->toContain('pollora-version--current">v13.40.0</span>')
            ->not->toContain('class="pollora-version pollora-version--update')
            ->toContain('href="https://pollora.dev"');
    });

    it('announces a newer release', function (): void {
        $html = renderDashboardPage('13.39.0', '13.40.0');

        expect($html)->toContain('pollora-version--update">v13.39.0 &rarr; v13.40.0 available</span>');
    });

    it('shows a development build without a v prefix', function (): void {
        $html = renderDashboardPage('dev-develop', '13.40.0');

        expect($html)->toContain('pollora-version--current">dev-develop</span>');
    });

    it('lists empty cards as none discovered and no modules', function (): void {
        $html = renderDashboardPage('13.40.0', '13.40.0');

        expect(substr_count($html, 'None discovered.'))->toBe(6)
            ->and($html)->toContain('No modules installed.')
            ->toContain('No performance data available.')
            ->toContain('Discovery Cache')
            ->toContain('pollora-badge--gray">Disabled</span>');
    });

    it('lists discovered entities with their metadata', function (): void {
        $html = renderDashboardPage('13.40.0', '13.40.0', [
            'hooks' => [['type' => 'action'], ['type' => 'filter'], ['type' => 'filter']],
            'wp_rest_routes' => [['class' => 'App\\Rest\\Posts']],
            'schedules' => [['class' => 'App\\Jobs\\Nightly', 'method' => 'run']],
        ]);

        expect($html)->toContain('<tr><td>Actions</td><td>1</td></tr>')
            ->toContain('<tr><td>Filters</td><td>2</td></tr>')
            ->toContain('<div class="pollora-entity-meta">App\\Rest\\Posts</div>')
            ->toContain('App\\Jobs\\Nightly::run()')
            ->and(substr_count($html, 'None discovered.'))->toBe(4);
    });

    it('shows the discovery performance counters that are present', function (): void {
        $html = renderDashboardPage('13.40.0', '13.40.0', stats: [
            'context' => ['cache_hits' => 3, 'cache_misses' => 2, 'classes_processed' => 40, 'discoveries_executed' => 6],
            'instance_pool' => ['total_instances' => 9],
            'static_cache_size' => 5,
        ]);

        expect($html)->toContain('<td>Cache hits</td><td>3</td>')
            ->toContain('<td>Cache misses</td><td>2</td>')
            ->toContain('<td>Classes processed</td><td>40</td>')
            ->toContain('<td>Discoveries executed</td><td>6</td>')
            ->toContain('<td>Instance pool size</td><td>9</td>')
            ->toContain('<td>Static cache entries</td><td>5</td>')
            ->not->toContain('No performance data available.');
    });

    it('omits performance counters that were not collected', function (): void {
        $html = renderDashboardPage('13.40.0', '13.40.0', stats: ['context' => ['cache_hits' => 1]]);

        expect($html)->toContain('<td>Cache hits</td><td>1</td>')
            ->not->toContain('Cache misses')
            ->not->toContain('Instance pool size');
    });

    it('warns that WordPress catalogues will not resolve when the override is inactive', function (): void {
        $html = renderDashboardPage('13.40.0', '13.40.0');

        // The collector reports the override state of the running process:
        // whichever it is, the warning must appear exactly when it is inactive.
        $active = str_contains($html, 'pollora-badge--green">Active</span>');

        expect(str_contains($html, 'WordPress catalogues will not resolve'))->toBe(! $active)
            ->and($html)->toContain('<td>WordPress locale</td><td><code>fr_FR</code></td>')
            ->toContain('<td>Laravel locale</td><td><code>Unknown</code></td>');
    });

    it('lists modules with their state, description and a link to manage them', function (): void {
        $shop = Mockery::mock(Module::class);
        $shop->shouldReceive('getName')->andReturn('Shop');
        $shop->shouldReceive('getDescription')->andReturn('Sells things');
        $shop->shouldReceive('get')->with('priority', '')->andReturn('0');
        $shop->shouldReceive('isEnabled')->andReturn(true);

        $blog = Mockery::mock(Module::class);
        $blog->shouldReceive('getName')->andReturn('Blog');
        $blog->shouldReceive('getDescription')->andReturn('');
        $blog->shouldReceive('get')->with('priority', '')->andReturn('1');
        $blog->shouldReceive('isEnabled')->andReturn(false);

        $repo = Mockery::mock();
        $repo->shouldReceive('all')->andReturn(['Shop' => $shop, 'Blog' => $blog]);
        $repo->shouldReceive('allEnabled')->andReturn(['shop' => $shop]);
        $repo->shouldReceive('allDisabled')->andReturn(['blog' => $blog]);

        $html = renderDashboardPage('13.40.0', '13.40.0', modules: $repo);

        expect($html)->toContain('pollora-badge--green">1</span>')
            ->toContain('pollora-entity-label">Shop</div><div class="pollora-entity-meta">Sells things</div>')
            ->toContain('pollora-entity-label">Blog</div></div>')
            ->toContain('pollora-badge--green">On</span>')
            ->toContain('pollora-badge--gray">Off</span>')
            ->toContain('https://example.test/wp-admin/plugins.php?page=')
            ->not->toContain('Stored in')
            ->not->toContain('No modules installed.');
    });
});
