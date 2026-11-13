<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Http;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Infrastructure\Services\DiscoveryCacheManager;
use Pollora\Doctor\Application\Services\Doctor;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Doctor\Infrastructure\Checks\AppUrlCheck;
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
use Pollora\Doctor\Infrastructure\Checks\PublicRootCheck;
use Pollora\Doctor\Infrastructure\Checks\SymlinkedDirectoryCheck;
use Pollora\Doctor\Infrastructure\Checks\TemplatePlaceholdersCheck;
use Pollora\Doctor\Infrastructure\Checks\WooCommercePackageCheck;
use Pollora\Doctor\Infrastructure\Checks\WordPressCorePatchCheck;
use Pollora\Doctor\Infrastructure\Checks\WpRocketPackageCheck;
use Pollora\Doctor\Infrastructure\Providers\DoctorServiceProvider;
use Pollora\Doctor\Infrastructure\Support\ProjectModule;
use Pollora\Doctor\Infrastructure\Support\ProjectModules;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Theme\Application\Services\ThemeAvailability;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;

// Declared only for these tests when WordPress is absent: the registry the block check reads.
if (! class_exists('WP_Block_Type_Registry')) {
    eval('final class WP_Block_Type_Registry {
        public static array $registered = [];
        public static function get_instance(): self { return new self; }
        public function is_registered(string $name): bool { return in_array($name, self::$registered, true); }
    }');
}

function covPut(string $path, string $contents = 'x'): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }

    file_put_contents($path, $contents);
}

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pollora-doctor-branches-'.uniqid();
    $this->theme = $this->root.'/themes/journal';
    mkdir($this->theme, 0777, true);
    mkdir($this->root.'/public', 0777, true);
    $this->previousBasePath = app()->basePath();
    app()->setBasePath($this->root);
    app()->usePublicPath($this->root.'/public');

    Functions\when('get_stylesheet_directory')->alias(fn (): string => $this->theme);
    Functions\when('get_stylesheet')->justReturn('journal');

    $this->modulesOf = function (ProjectModule ...$modules): ProjectModules {
        $mock = Mockery::mock(ProjectModules::class);
        $mock->shouldReceive('all')->andReturn(array_values($modules));

        return $mock;
    };
});

afterEach(function (): void {
    app()->setBasePath($this->previousBasePath);
    (new Filesystem)->deleteDirectory($this->root);
});

/** @param class-string<CheckInterface> $class */
function covBareCheck(string $class): CheckInterface
{
    return (new ReflectionClass($class))->newInstanceWithoutConstructor();
}

describe('Check identity', function (): void {
    it('keeps the stable id, the label and the contexts of every check', function (string $class, string $id, string $label, array $contexts): void {
        $check = covBareCheck($class);

        expect($check->id())->toBe($id)
            ->and($check->label())->toBe($label)
            ->and($check->runsIn())->toBe($contexts);
    })->with(function (): array {
        $both = [RunContext::Console, RunContext::Http];

        return [
            [AppUrlCheck::class, 'app-url', 'Application URL', $both],
            [AssetBuildCheck::class, 'asset-builds', 'Theme, plugin and module builds', $both],
            [BlockRegistrationCheck::class, 'block-registration', 'Blocks registered', [RunContext::Http]],
            [BlockThemeRoutesCheck::class, 'block-theme-routes', 'Routes over block templates', $both],
            [DevelopmentCachesCheck::class, 'development-caches', 'Configuration and route caches', $both],
            [DiscoveryCacheCheck::class, 'discovery-cache', 'Discovery cache', [RunContext::Console]],
            [EnvironmentFileCheck::class, 'environment-file', 'Environment file', $both],
            [LegacyBlocksDirectoryCheck::class, 'legacy-blocks-directory', 'Blocks in the legacy folder', $both],
            [PatchesLockCheck::class, 'patches-lock', 'Composer patches lock', $both],
            [PatternCacheCheck::class, 'pattern-cache', 'Theme pattern cache', $both],
            [PatternFilesCheck::class, 'pattern-files', 'Theme pattern files', $both],
            [PublicRootCheck::class, 'public-root', 'Document root', [RunContext::Http]],
            [SymlinkedDirectoryCheck::class, 'symlinked-directories', 'Symlinked theme, plugin and module directories', $both],
            [TemplatePlaceholdersCheck::class, 'template-placeholders', 'Template placeholders', $both],
            [WooCommercePackageCheck::class, 'woocommerce-package', 'WooCommerce integration', $both],
            [WordPressCorePatchCheck::class, 'wordpress-core-patch', 'WordPress core patch', $both],
            [WpRocketPackageCheck::class, 'wp-rocket-package', 'WP Rocket integration', $both],
        ];
    });
});

describe('Configuration and route caches', function (): void {
    function covCachesApp(string $environment, bool $config, bool $routes): Application
    {
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('environment')->andReturnUsing(fn (string ...$envs): string|bool => $envs === [] ? $environment : in_array($environment, $envs, true));
        $app->shouldReceive('configurationIsCached')->andReturn($config);
        $app->shouldReceive('routesAreCached')->andReturn($routes);
        $app->shouldReceive('getCachedConfigPath')->andReturn('/cache/config.php');
        $app->shouldReceive('getCachedRoutesPath')->andReturn('/cache/routes.php');

        return $app;
    }

    it('expects caches in production, whatever is cached', function (): void {
        $result = (new DevelopmentCachesCheck(covCachesApp('production', true, true)))->run(RunContext::Console);

        expect($result->status->value)->toBe('ok');
    });

    it('warns about a cached configuration outside production and says how to clear it', function (): void {
        $result = (new DevelopmentCachesCheck(covCachesApp('local', true, false)))->run(RunContext::Console);

        expect($result->status->value)->toBe('warning')
            ->and($result->summary)->toBe('Caches meant for production are on in the local environment.')
            ->and($result->details)->toHaveCount(1)
            ->and($result->details[0])->toContain('configuration cached (/cache/config.php)')
            ->and($result->fix)->toBe('php artisan optimize:clear');
    });

    it('warns about cached routes outside production', function (): void {
        $result = (new DevelopmentCachesCheck(covCachesApp('staging', false, true)))->run(RunContext::Console);

        expect($result->status->value)->toBe('warning')
            ->and($result->details)->toHaveCount(1)
            ->and($result->details[0])->toContain('routes cached (/cache/routes.php)');
    });

    it('names both caches when both are on', function (): void {
        $result = (new DevelopmentCachesCheck(covCachesApp('local', true, true)))->run(RunContext::Console);

        expect($result->details)->toHaveCount(2);
    });

    it('passes when nothing is cached', function (): void {
        $result = (new DevelopmentCachesCheck(covCachesApp('local', false, false)))->run(RunContext::Console);

        expect($result->status->value)->toBe('ok');
    });
});

describe('Routes over block templates (no conflict)', function (): void {
    it('passes on a block theme when no Route::wp() condition answers', function (): void {
        Functions\when('wp_is_block_theme')->justReturn(true);
        $router = Mockery::mock(Router::class);
        $router->shouldReceive('getRoutes')->andReturn(new RouteCollection);

        $result = (new BlockThemeRoutesCheck($router))->run(RunContext::Console);

        expect($result->status->value)->toBe('ok');
    });
});

describe('Discovery cache (details)', function (): void {
    function covDiscoveryCheck(bool $enabled, array $missing): DiscoveryCacheCheck
    {
        $location = Mockery::mock(DiscoveryLocationInterface::class);
        $manager = Mockery::mock(DiscoveryManager::class);
        $manager->shouldReceive('getLocations')->andReturn(collect([$location]));
        $cache = Mockery::mock(DiscoveryCacheManager::class);
        $cache->shouldReceive('isCacheEnabled')->andReturn($enabled);
        $cache->shouldReceive('classesMissingFromCache')->with($location)->andReturn($missing);

        return new DiscoveryCacheCheck($manager, $cache);
    }

    it('passes when the cache holds every class on disk', function (): void {
        expect(covDiscoveryCheck(true, [])->run(RunContext::Console)->status->value)->toBe('ok');
    });

    it('lists the missing classes sorted, at most twenty, while counting them all', function (): void {
        $missing = array_map(fn (int $i): string => sprintf('App\\C%02d', $i), range(25, 1));

        $result = covDiscoveryCheck(true, $missing)->run(RunContext::Console);

        expect($result->summary)->toBe('25 class(es) added since the cache was written are not registered.')
            ->and($result->details)->toHaveCount(20)
            ->and($result->details[0])->toBe('App\\C01')
            ->and($result->details[19])->toBe('App\\C20');
    });
});

describe('Environment file (absent)', function (): void {
    it('is skipped without a .env file', function (): void {
        $result = (new EnvironmentFileCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('skipped');
    });
});

describe('Blocks in the legacy folder', function (): void {
    it('warns about each module that keeps a block.json in resources/blocks', function (): void {
        covPut($this->theme.'/resources/blocks/hero/block.json', '{}');
        covPut($this->theme.'/resources/blocks/hero/render.php', '');
        $theme = new ProjectModule('theme', 'journal', $this->theme, 'h', 'b');
        $clean = new ProjectModule('plugin', 'acme', $this->root.'/plugins/acme', 'h', 'b');

        $result = (new LegacyBlocksDirectoryCheck(($this->modulesOf)($theme, $clean)))->run(RunContext::Console);

        expect($result->status->value)->toBe('warning')
            ->and($result->details)->toBe(['theme journal: 1 block(s) in themes/journal/resources/blocks'])
            ->and($result->summary)->toBe('1 module(s) keep blocks in resources/blocks, which stops loading in v15.');
    });

    it('passes when no module keeps blocks there', function (): void {
        $theme = new ProjectModule('theme', 'journal', $this->theme, 'h', 'b');

        $result = (new LegacyBlocksDirectoryCheck(($this->modulesOf)($theme)))->run(RunContext::Console);

        expect($result->status->value)->toBe('ok');
    });
});

describe('Theme patterns (branches)', function (): void {
    beforeEach(function (): void {
        Functions\when('wp_is_development_mode')->justReturn(false);
    });

    it('skips the pattern checks when WordPress reports no theme directory', function (): void {
        Functions\when('get_stylesheet_directory')->justReturn('');

        expect((new PatternFilesCheck)->run(RunContext::Console)->status->value)->toBe('skipped')
            ->and((new PatternCacheCheck)->run(RunContext::Console)->status->value)->toBe('skipped');
    });

    it('has nothing to say about a theme without a patterns directory', function (): void {
        expect((new PatternFilesCheck)->run(RunContext::Console)->status->value)->toBe('ok')
            ->and((new PatternFilesCheck)->run(RunContext::Console)->summary)->toBe('The theme has no patterns/ directory.');
    });

    it('passes a theme whose pattern files all carry a Title and a Slug', function (): void {
        covPut($this->theme.'/patterns/hero.php', "<?php\n/**\n * Title: Hero\n * Slug: journal/hero\n */");

        $result = (new PatternFilesCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('ok')
            ->and($result->summary)->toBe('Every pattern file is one WordPress registers.');
    });

    it('counts the html patterns and the headerless ones together', function (): void {
        covPut($this->theme.'/patterns/a.html', '');
        covPut($this->theme.'/patterns/b.php', '<?php');

        $result = (new PatternFilesCheck)->run(RunContext::Console);

        expect($result->summary)->toBe('2 pattern file(s) are never registered by WordPress.')
            ->and($result->details)->toBe([
                'patterns/a.html: WordPress reads only .php files in patterns/',
                'patterns/b.php: no Title or Slug in its header',
            ]);
    });

    it('does not flag a theme with no pattern file in the cache check', function (): void {
        Functions\when('wp_get_theme')->justReturn(new stdClass);

        $result = (new PatternCacheCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('ok')
            ->and($result->summary)->toBe('The theme has no pattern files.');
    });

    it('passes when the cached list of WordPress matches patterns/', function (): void {
        covPut($this->theme.'/patterns/hero.php', "<?php\n/**\n * Title: Hero\n * Slug: journal/hero\n */");
        Functions\when('wp_get_theme')->justReturn(new class
        {
            public function get_block_patterns(): array
            {
                return ['hero.php' => ['slug' => 'journal/hero']];
            }
        });

        $result = (new PatternCacheCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('ok')
            ->and($result->summary)->toBe("WordPress's cached list matches patterns/.");
    });

    it('ignores a pattern file without a header in the cache check, since WordPress never caches it', function (): void {
        covPut($this->theme.'/patterns/headerless.php', '<?php');
        Functions\when('wp_get_theme')->justReturn(new stdClass);

        expect((new PatternCacheCheck)->run(RunContext::Console)->summary)->toBe('The theme has no pattern files.');
    });
});

describe('Template placeholders', function (): void {
    it('fails for a theme still carrying its template placeholders and gives the command to regenerate it', function (): void {
        covPut($this->theme.'/style.css', '/* Theme Name: %theme_name% */');
        covPut($this->theme.'/functions.php', '<?php // clean');
        $theme = new ProjectModule('theme', 'journal', $this->theme, 'h', 'b');

        $result = (new TemplatePlaceholdersCheck(($this->modulesOf)($theme)))->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->summary)->toBe('1 module(s) were copied from a template instead of generated: they still carry its placeholders.')
            ->and($result->details)->toBe(['theme journal: style.css'])
            ->and($result->fix)->toBe('php artisan pollora:make:theme journal --repository=<owner/template-repository>');
    });

    it('catches a placeholder in a file name, and any .stub file', function (): void {
        covPut($this->theme.'/%THEME_SLUG%.php', '<?php');
        covPut($this->theme.'/block.stub', 'stub');
        $theme = new ProjectModule('theme', 'journal', $this->theme, 'h', 'b');

        $result = (new TemplatePlaceholdersCheck(($this->modulesOf)($theme)))->run(RunContext::Console);

        expect($result->details)->toBe(['theme journal: %THEME_SLUG%.php, block.stub']);
    });

    it('lists five files and counts the rest', function (): void {
        foreach (range(1, 8) as $i) {
            covPut($this->theme.'/part'.$i.'.php', '<?php // %theme_x%');
        }

        $theme = new ProjectModule('theme', 'journal', $this->theme, 'h', 'b');

        $result = (new TemplatePlaceholdersCheck(($this->modulesOf)($theme)))->run(RunContext::Console);

        expect($result->details)->toBe(['theme journal: part1.php, part2.php, part3.php, part4.php, part5.php (+3)']);
    });

    it('looks for the plugin placeholders in a plugin, and joins one fix per module', function (): void {
        $pluginRoot = $this->root.'/plugins/acme';
        covPut($pluginRoot.'/acme.php', '<?php // %plugin_name%');
        covPut($this->theme.'/style.css', '%theme_name%');

        $result = (new TemplatePlaceholdersCheck(($this->modulesOf)(
            new ProjectModule('theme', 'journal', $this->theme, 'h', 'b'),
            new ProjectModule('plugin', 'acme', $pluginRoot, 'h', 'b'),
        )))->run(RunContext::Console);

        expect($result->summary)->toStartWith('2 module(s)')
            ->and($result->fix)->toBe('php artisan pollora:make:theme journal --repository=<owner/template-repository> ; php artisan pollora:make:plugin acme');
    });

    it('does not look at Laravel modules, nor mix up the theme and plugin placeholders', function (): void {
        $moduleRoot = $this->root.'/Modules/Shop';
        covPut($moduleRoot.'/a.php', '<?php // %theme_name% %plugin_name%');
        covPut($this->theme.'/style.css', '/* %plugin_name% */');

        $result = (new TemplatePlaceholdersCheck(($this->modulesOf)(
            new ProjectModule('module', 'Shop', $moduleRoot, 'h', 'b'),
            new ProjectModule('theme', 'journal', $this->theme, 'h', 'b'),
        )))->run(RunContext::Console);

        expect($result->status->value)->toBe('ok')
            ->and($result->summary)->toBe('No template placeholder left.');
    });
});

describe('Block registration (branches)', function (): void {
    beforeEach(function (): void {
        WP_Block_Type_Registry::$registered = ['journal/hero'];
    });

    afterEach(function (): void {
        WP_Block_Type_Registry::$registered = [];
    });

    it('fails with the blocks that WordPress does not know, and one that has no name', function (): void {
        covPut($this->theme.'/resources/views/blocks/hero/block.json', '{"name": "journal/hero"}');
        covPut($this->theme.'/resources/views/blocks/ghost/block.json', '{"name": "journal/ghost"}');
        covPut($this->theme.'/resources/views/blocks/nameless/block.json', '{"title": "No name"}');
        $theme = new ProjectModule('theme', 'journal', $this->theme, 'h', 'b');

        $result = (new BlockRegistrationCheck(($this->modulesOf)($theme)))->run(RunContext::Http);

        expect($result->status->value)->toBe('error')
            ->and($result->summary)->toBe('2 block(s) are not registered: the editor and the pages do not have them.')
            ->and($result->details)->toContain('theme journal: journal/ghost (resources/views/blocks/ghost/block.json)')
            ->toContain('theme journal: resources/views/blocks/nameless/block.json has no "name"')
            ->not->toContain('theme journal: journal/hero (resources/views/blocks/hero/block.json)');
    });

    it('passes when every block.json is registered', function (): void {
        covPut($this->theme.'/resources/views/blocks/hero/block.json', '{"name": "journal/hero"}');
        $theme = new ProjectModule('theme', 'journal', $this->theme, 'h', 'b');

        $result = (new BlockRegistrationCheck(($this->modulesOf)($theme)))->run(RunContext::Http);

        expect($result->status->value)->toBe('ok');
    });
});

function covThemeAvailable(): ThemeAvailability
{
    $registrar = Mockery::mock(ThemeRegistrarInterface::class);
    $registrar->shouldReceive('getActiveTheme')->andReturn(Mockery::mock(ThemeModuleInterface::class));
    $container = new Container;
    $container->instance(ThemeRegistrarInterface::class, $registrar);

    return new ThemeAvailability($container);
}

describe('Asset builds (dev server probe)', function (): void {
    it('treats a dev server that answers 2xx on its client as running', function (): void {
        Http::fake(['*' => Http::response('', 200)]);
        covPut($this->theme.'/vite.config.js', '');
        covPut($this->root.'/public/theme.hot', 'http://localhost:5173/');
        $theme = new ProjectModule('theme', 'journal', $this->theme, $this->root.'/public/theme.hot', 'build/theme');
        $availability = covThemeAvailable();

        $result = (new AssetBuildCheck($availability, ($this->modulesOf)($theme)))->run(RunContext::Console);

        expect($result->status->value)->toBe('ok');
        Http::assertSent(fn ($request): bool => $request->url() === 'http://localhost:5173/@vite/client');
    });

    it('reports a hot file whose dev server does not answer', function (): void {
        Http::fake(fn (): never => throw new RuntimeException('connection refused'));
        covPut($this->theme.'/vite.config.js', '');
        covPut($this->root.'/public/theme.hot', 'http://localhost:5173');
        $theme = new ProjectModule('theme', 'journal', $this->theme, $this->root.'/public/theme.hot', 'build/theme');
        $availability = covThemeAvailable();

        $result = (new AssetBuildCheck($availability, ($this->modulesOf)($theme)))->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->details[0])->toContain('where no Vite dev server answers');
    });
});

describe('DoctorServiceProvider', function (): void {
    it('tags the checks in reading order and builds the doctor from them', function (): void {
        $this->app->instance(DiscoveryManager::class, Mockery::mock(DiscoveryManager::class));
        $this->app->instance(DiscoveryCacheManager::class, Mockery::mock(DiscoveryCacheManager::class));

        $provider = new DoctorServiceProvider($this->app);
        $provider->register();

        $tagged = array_map(fn ($check) => $check::class, iterator_to_array($this->app->tagged(DoctorServiceProvider::CHECKS_TAG), false));

        expect($tagged)->toHaveCount(17)
            ->and($tagged[0])->toBe(WordPressCorePatchCheck::class)
            ->and($tagged[16])->toBe(WpRocketPackageCheck::class)
            ->and($this->app->make(Doctor::class))->toBe($this->app->make(Doctor::class));
    });

    it('adds the checks that run over HTTP to the Site Health tests', function (): void {
        $registered = null;
        $filter = Mockery::mock(Filter::class);
        $filter->shouldReceive('add')->with('site_status_tests', Mockery::type('Closure'))
            ->andReturnUsing(function (string $hook, Closure $callback) use (&$registered, $filter) {
                $registered = $callback;

                return $filter;
            });

        $httpCheck = new class implements CheckInterface
        {
            public function id(): string
            {
                return 'fake-check';
            }

            public function label(): string
            {
                return 'Fake';
            }

            public function runsIn(): array
            {
                return [RunContext::Http];
            }

            public function run(RunContext $context): CheckResult
            {
                return CheckResult::ok('fine');
            }
        };
        $this->app->instance(Doctor::class, new Doctor([$httpCheck]));

        (new DoctorServiceProvider($this->app))->boot($filter);
        $tests = $registered(['direct' => ['existing' => ['label' => 'kept']]]);

        expect($tests['direct'])->toHaveKeys(['existing', 'pollora_fake_check'])
            ->and($tests['direct']['pollora_fake_check']['label'])->toBe('Fake')
            ->and($tests['direct']['pollora_fake_check']['test']()['status'])->toBe('good');
    });
});
