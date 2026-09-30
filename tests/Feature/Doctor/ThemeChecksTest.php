<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Application\Services\AssetRetrievalService;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Infrastructure\Checks\BlockRegistrationCheck;
use Pollora\Doctor\Infrastructure\Checks\BlockThemeRoutesCheck;
use Pollora\Doctor\Infrastructure\Checks\PatternCacheCheck;
use Pollora\Doctor\Infrastructure\Checks\PatternFilesCheck;
use Pollora\Doctor\Infrastructure\Checks\ThemeBuildCheck;
use Pollora\Doctor\Infrastructure\Checks\ThemeDirectoryCheck;
use Pollora\Doctor\Infrastructure\Checks\ThemePlaceholdersCheck;
use Pollora\Route\Infrastructure\Models\Route;
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

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pollora-doctor-theme-'.uniqid();
    $this->theme = $this->root.'/themes/journal';
    mkdir($this->theme, 0777, true);
    Functions\when('get_stylesheet_directory')->alias(fn (): string => $this->theme);
    Functions\when('get_stylesheet')->justReturn('journal');
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->root);
});

if (! function_exists('putFile')) {
    function putFile(string $path, string $contents): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }
}

describe('Theme and its build', function (): void {
    function buildCheck(string $root, bool $themeMissing): ThemeBuildCheck
    {
        $registrar = Mockery::mock(ThemeRegistrarInterface::class);
        $registrar->shouldReceive('getActiveTheme')->andReturn($themeMissing ? null : Mockery::mock(ThemeModuleInterface::class));
        $container = new Container;
        $container->instance(ThemeRegistrarInterface::class, $registrar);

        $assets = new AssetManager(Mockery::mock(AssetRetrievalService::class));
        $assets->addContainer('theme', [
            'hot_file' => $root.'/public/journal.hot',
            'build_directory' => 'build/theme/journal',
            'manifest_path' => 'manifest.json',
        ]);
        app()->usePublicPath($root.'/public');

        return new ThemeBuildCheck(new ThemeAvailability($container), $assets);
    }

    it('fails when no theme is installed', function (): void {
        Functions\when('get_stylesheet_directory')->justReturn($this->root.'/themes/missing');

        expect(buildCheck($this->root, themeMissing: true)->run(RunContext::Console)->status->value)->toBe('error');
    });

    it('fails when a Vite theme has neither a manifest nor a dev server, and says where to build', function (): void {
        putFile($this->theme.'/vite.config.js', 'export default {}');
        $this->app->setBasePath($this->root);

        $result = buildCheck($this->root, themeMissing: false)->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->fix)->toBe('cd themes/journal && npm install && npm run build');
    });

    it('passes a built theme, and one served by the dev server', function (): void {
        putFile($this->theme.'/vite.config.js', 'export default {}');
        putFile($this->root.'/public/build/theme/journal/manifest.json', '{}');
        expect(buildCheck($this->root, themeMissing: false)->run(RunContext::Console)->status->value)->toBe('ok');

        unlink($this->root.'/public/build/theme/journal/manifest.json');
        putFile($this->root.'/public/journal.hot', 'https://localhost:5173');
        expect(buildCheck($this->root, themeMissing: false)->run(RunContext::Console)->status->value)->toBe('ok');
    });
});

describe('Theme directory', function (): void {
    it('fails when the theme is a symlink to a directory of another name', function (): void {
        (new Filesystem)->deleteDirectory($this->theme);
        mkdir($this->root.'/repos/theme-journal', 0777, true);
        symlink($this->root.'/repos/theme-journal', $this->theme);

        $result = (new ThemeDirectoryCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->summary)->toContain('public/build/theme/theme-journal');
    });

    it('passes a real directory', function (): void {
        expect((new ThemeDirectoryCheck)->run(RunContext::Console)->status->value)->toBe('ok');
    });
});

describe('Theme placeholders', function (): void {
    it('fails on a template copied instead of generated', function (): void {
        putFile($this->theme.'/app/Providers/AssetServiceProvider.php', "<?php\nnamespace %theme_namespace%\\Providers;\n");
        putFile($this->theme.'/node_modules/pkg/index.js', '%theme_name%');

        $result = (new ThemePlaceholdersCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('error')
            ->and($result->details)->toBe(['app/Providers/AssetServiceProvider.php']);
    });

    it('passes a generated theme', function (): void {
        putFile($this->theme.'/app/Providers/AssetServiceProvider.php', "<?php\nnamespace Theme\\Journal\\Providers;\n");

        expect((new ThemePlaceholdersCheck)->run(RunContext::Console)->status->value)->toBe('ok');
    });
});

describe('Theme pattern files', function (): void {
    it('warns about an .html pattern and a pattern without a Slug', function (): void {
        putFile($this->theme.'/patterns/masthead.html', '<!-- wp:paragraph /-->');
        putFile($this->theme.'/patterns/footer.php', "<?php\n/**\n * Title: Footer\n */\n?>");
        putFile($this->theme.'/patterns/header.php', "<?php\n/**\n * Title: Header\n * Slug: journal/header\n */\n?>");

        $result = (new PatternFilesCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('warning')
            ->and($result->details)->toBe([
                'patterns/masthead.html: WordPress reads only .php files in patterns/',
                'patterns/footer.php: no Title or Slug in its header',
            ]);
    });
});

describe('Theme pattern cache', function (): void {
    beforeEach(function (): void {
        putFile($this->theme.'/patterns/header.php', "<?php\n/**\n * Title: Header\n * Slug: journal/header\n */\n?>");
        putFile($this->theme.'/patterns/new.php', "<?php\n/**\n * Title: New\n * Slug: journal/new\n */\n?>");
        Functions\when('wp_is_development_mode')->justReturn(false);
    });

    it("warns about a pattern file missing from WordPress's cached list", function (): void {
        Functions\when('wp_get_theme')->justReturn(new class
        {
            public function get_block_patterns(): array
            {
                return ['header.php' => ['slug' => 'journal/header']];
            }
        });

        $result = (new PatternCacheCheck)->run(RunContext::Console);

        expect($result->status->value)->toBe('warning')
            ->and($result->details)->toBe(['patterns/new.php']);
    });

    it('passes in theme development mode, where WordPress does not cache', function (): void {
        Functions\when('wp_is_development_mode')->justReturn(true);
        Functions\when('wp_get_theme')->justReturn(new class
        {
            public function get_block_patterns(): array
            {
                return [];
            }
        });

        expect((new PatternCacheCheck)->run(RunContext::Console)->status->value)->toBe('ok');
    });
});

describe('Routes over block templates', function (): void {
    it('warns about a Route::wp() route on a block theme', function (): void {
        Functions\when('wp_is_block_theme')->justReturn(true);
        $route = (new Route(['GET'], 'single_x', fn (): string => ''))->setIsWordPressRoute(true)->setCondition('is_single');
        $routes = new RouteCollection;
        $routes->add($route);

        $router = Mockery::mock(Router::class);
        $router->shouldReceive('getRoutes')->andReturn($routes);

        $result = (new BlockThemeRoutesCheck($router))->run(RunContext::Console);

        expect($result->status->value)->toBe('warning')
            ->and($result->details[0])->toStartWith("Route::wp('is_single')");
    });

    it('does not apply to a classic theme', function (): void {
        Functions\when('wp_is_block_theme')->justReturn(false);

        expect((new BlockThemeRoutesCheck(Mockery::mock(Router::class)))->run(RunContext::Console)->status->value)->toBe('skipped');
    });
});

describe('Theme blocks registered', function (): void {
    beforeEach(function (): void {
        putFile($this->theme.'/resources/views/blocks/hero/block.json', '{"name": "journal/hero"}');
        putFile($this->theme.'/resources/views/blocks/card/block.json', '{"name": "journal/card"}');
    });

    it('fails when a block of the theme is not registered in the request', function (): void {
        WP_Block_Type_Registry::$registered = ['journal/hero'];

        $result = (new BlockRegistrationCheck)->run(RunContext::Http);

        expect($result->status->value)->toBe('error')
            ->and($result->details)->toBe(['journal/card (resources/views/blocks/card/block.json)']);
    });

    it('passes when every block is registered', function (): void {
        WP_Block_Type_Registry::$registered = ['journal/hero', 'journal/card'];

        expect((new BlockRegistrationCheck)->run(RunContext::Http)->status->value)->toBe('ok');
    });
});
