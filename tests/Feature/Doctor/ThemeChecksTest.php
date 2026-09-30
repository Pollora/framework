<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Infrastructure\Checks\BlockThemeRoutesCheck;
use Pollora\Doctor\Infrastructure\Checks\PatternCacheCheck;
use Pollora\Doctor\Infrastructure\Checks\PatternFilesCheck;
use Pollora\Route\Infrastructure\Models\Route;

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
