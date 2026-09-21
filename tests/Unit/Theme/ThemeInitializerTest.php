<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Mockery as m;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Theme\Domain\Contracts\ContainerInterface;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Contracts\WordPressThemeInterface;
use Pollora\Theme\Domain\Models\ThemeInitializer;

require_once __DIR__.'/../helpers.php';

/**
 * What this file is actually for.
 *
 * ThemeInitializer decides the value WordPress gets for `stylesheet_root` and
 * `template_root`, and getting it wrong is what fix 4 of v13.32.0-beta.3 was:
 * the front end renders perfectly while `wp_get_theme()` reports the theme
 * missing, because that option points at the theme's own directory instead of
 * the directory that holds it. WordPress joins the root with the stylesheet
 * name, so one level too deep gives `themes/default/default`.
 *
 * The decision lives in a private method with six collaborators, which is why
 * it had no test and was only ever verified on a live site. It has a seam all
 * the same: every collaborator arrives through the container, and the method
 * is reached through the `after_setup_theme` callback the initializer
 * registers. Capturing that callback is enough to drive it.
 */

/**
 * The constructor falls back to base_path('themes'), which Laravel's helper
 * resolves through the container — eagerly, as a default argument, whether or
 * not the fallback is used. So the container has to answer.
 */
beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();

    Container::setInstance(new class extends Container
    {
        public function basePath($path = ''): string
        {
            return '/srv/site'.($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
        }
    });
});

afterEach(function (): void {
    Container::setInstance($this->previousContainer);
});

/**
 * Build an initializer whose collaborators are all doubles, and hand back the
 * hooks it registered so the test can fire them.
 *
 * @param  string|null  $themePath  the active theme's own directory, or null for no active theme
 * @return array{initializer: ThemeInitializer, actions: array<string, callable>, filters: array<string, callable>, directories: ArrayObject<int, string>}
 */
function initializerUnderTest(?string $themePath): array
{
    $actions = [];
    $filters = [];
    // An ArrayObject, not an array: the closure below records into it after
    // this function has already returned its result.
    $directories = new ArrayObject;

    // add() returns the hook object for chaining, so the doubles have to hand
    // themselves back rather than null.
    $action = m::mock(Action::class);
    $action->shouldReceive('add')->andReturnUsing(
        function (string $hook, callable $callback, ...$rest) use (&$actions, &$action) {
            $actions[$hook] = $callback;

            return $action;
        }
    );

    $filter = m::mock(Filter::class);
    $filter->shouldReceive('add')->andReturnUsing(
        function (string $hook, callable $callback, ...$rest) use (&$filters, &$filter) {
            $filters[$hook] = $callback;

            return $filter;
        }
    );

    $wpTheme = m::mock(WordPressThemeInterface::class);
    $wpTheme->shouldReceive('registerThemeDirectory')->andReturnUsing(
        function (string $path) use (&$directories): bool {
            $directories[] = $path;

            return true;
        }
    );
    $wpTheme->shouldReceive('getTheme')->andReturn(new stdClass);

    $module = null;

    if ($themePath !== null) {
        $module = m::mock(ThemeModuleInterface::class);
        $module->shouldReceive('getPath')->andReturn($themePath);
        $module->shouldReceive('getName')->andReturn(basename($themePath));
    }

    $registrar = m::mock(ThemeRegistrarInterface::class);
    $registrar->shouldReceive('getActiveTheme')->andReturn($module);

    $themeService = m::mock(ThemeService::class);
    $themeService->shouldReceive('load')->andReturnNull();

    $container = m::mock(ContainerInterface::class);
    $container->shouldReceive('get')->with(Action::class)->andReturn($action);
    $container->shouldReceive('get')->with(Filter::class)->andReturn($filter);
    $container->shouldReceive('get')->with(WordPressThemeInterface::class)->andReturn($wpTheme);
    $container->shouldReceive('get')->with(ThemeRegistrarInterface::class)->andReturn($registrar);
    $container->shouldReceive('get')->with(ThemeService::class)->andReturn($themeService);
    $container->shouldReceive('get')->andReturn($themeService);
    $container->shouldReceive('registerProvider')->andReturnNull();
    $container->shouldReceive('bindShared')->andReturnNull();

    $config = m::mock(ConfigRepositoryInterface::class);
    $config->shouldReceive('get')->andReturnUsing(fn (string $key, mixed $default = null): mixed => $default);

    $initializer = new ThemeInitializer($container, $config);
    $initializer->register();

    return [
        'initializer' => $initializer,
        'actions' => $actions,
        'filters' => $filters,
        'directories' => $directories,
    ];
}

describe('ThemeInitializer', function (): void {
    it('registers the directory that holds the theme, not the theme itself', function (): void {
        $harness = initializerUnderTest('/srv/site/themes/default');

        ($harness['actions']['after_setup_theme'])();

        // One level up. WordPress appends the stylesheet name to this, so the
        // theme's own path here would resolve to themes/default/default and
        // wp_get_theme() would report the theme missing.
        expect($harness['directories']->getArrayCopy())->toBe(['/srv/site/themes']);
    });

    it('feeds stylesheet_root and template_root the same directory', function (): void {
        $harness = initializerUnderTest('/srv/site/themes/default');

        ($harness['actions']['after_setup_theme'])();

        // Both options exist and WordPress consults them separately;
        // disagreeing is what makes the admin and the front end differ.
        expect(($harness['filters']['pre_option_stylesheet_root'])(false))->toBe('/srv/site/themes')
            ->and(($harness['filters']['pre_option_template_root'])(false))->toBe('/srv/site/themes');
    });

    it('overrides whatever stale absolute path wp_options holds', function (): void {
        $harness = initializerUnderTest('/srv/site/themes/default');

        ($harness['actions']['after_setup_theme'])();

        // The filter is pre_option_*, so it is handed the stored value. A site
        // moved between machines carries the old absolute path in the
        // database; ignoring the argument is the point.
        expect(($harness['filters']['pre_option_stylesheet_root'])('/home/old/build/themes'))
            ->toBe('/srv/site/themes');
    });

    it('handles a theme nested deeper than one level', function (): void {
        $harness = initializerUnderTest('/srv/site/public/content/themes/apiary');

        ($harness['actions']['after_setup_theme'])();

        expect($harness['directories']->getArrayCopy())->toBe(['/srv/site/public/content/themes']);
    });

    it('registers no directory when no theme is active', function (): void {
        $harness = initializerUnderTest(null);

        ($harness['actions']['after_setup_theme'])();

        // A site with no theme is a supported state — it is what the web
        // installer leaves behind — and must not have a root invented for it.
        expect($harness['directories']->getArrayCopy())->toBe([]);
    });
});
