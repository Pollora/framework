<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\View\ViewFinderInterface;
use Mockery as m;
use Pollora\View\Application\UseCases\MakeTemplateIncludableUseCase;
use Pollora\View\Application\UseCases\ResolveBladeTemplateUseCase;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;
use Pollora\View\Infrastructure\Services\WordPressTemplateHierarchyFilter;

function hierarchyFilter(
    ?TemplateFinderInterface $finder = null,
    ?ResolveBladeTemplateUseCase $resolve = null,
    ?ViewFinderInterface $views = null,
    ?MakeTemplateIncludableUseCase $includable = null,
): WordPressTemplateHierarchyFilter {
    return new WordPressTemplateHierarchyFilter(
        $finder ?? m::mock(TemplateFinderInterface::class),
        $resolve ?? m::mock(ResolveBladeTemplateUseCase::class),
        $views ?? m::mock(ViewFinderInterface::class),
        $includable ?? m::mock(MakeTemplateIncludableUseCase::class),
    );
}

function hierarchyViewsDir(array $files): string
{
    $dir = sys_get_temp_dir().'/pollora-hierarchy-'.bin2hex(random_bytes(6));
    mkdir($dir);
    foreach ($files as $name => $content) {
        $path = $dir.'/'.$name;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
    }

    return $dir;
}

function hierarchyRemove(string $dir): void
{
    (new Illuminate\Filesystem\Filesystem)->deleteDirectory($dir);
}

describe('WordPressTemplateHierarchyFilter hierarchy', function (): void {
    it('puts the located Blade templates before the WordPress candidates on a classic theme', function (): void {
        Functions\when('wp_is_block_theme')->justReturn(false);
        $finder = m::mock(TemplateFinderInterface::class);
        $finder->shouldReceive('locate')->once()->with(['single-post.php', 'single.php'])->andReturn(['/v/single.blade.php']);

        expect(hierarchyFilter($finder)->extendTemplateHierarchy(['single-post.php', 'single.php']))
            ->toBe(['/v/single.blade.php', 'single-post.php', 'single.php']);
    });

    it('also keeps the classic order when a block theme does not support block templates', function (): void {
        Functions\when('wp_is_block_theme')->justReturn(true);
        Functions\when('current_theme_supports')->justReturn(false);
        $finder = m::mock(TemplateFinderInterface::class);
        $finder->shouldReceive('locate')->andReturn(['/v/page.blade.php']);

        expect(hierarchyFilter($finder)->extendTemplateHierarchy(['page.php']))->toBe(['/v/page.blade.php', 'page.php']);
    });

    it('groups candidates by base name on a block theme, keeping the first of each family first', function (): void {
        Functions\when('wp_is_block_theme')->justReturn(true);
        Functions\when('current_theme_supports')->alias(fn (string $feature): bool => $feature === 'block-templates');
        Functions\when('get_page_template_slug')->justReturn('');
        $finder = m::mock(TemplateFinderInterface::class);
        $finder->shouldReceive('locate')->andReturn(['/v/page.blade.php']);

        $result = hierarchyFilter($finder)->extendTemplateHierarchy(['page.php', 'index.php']);

        expect($result)->toBe(['page.php', '/v/page.blade.php', 'index.php']);
    });

    it('puts the Blade template of the chosen page template first on a block theme', function (): void {
        Functions\when('wp_is_block_theme')->justReturn(true);
        Functions\when('current_theme_supports')->alias(fn (string $feature): bool => $feature === 'block-templates');
        Functions\when('get_page_template_slug')->justReturn('landing');
        $finder = m::mock(TemplateFinderInterface::class);
        $finder->shouldReceive('locate')->andReturn(['/v/landing.blade.php', '/v/page.blade.php']);

        $result = hierarchyFilter($finder)->extendTemplateHierarchy(['page.php']);

        expect($result[0])->toBe('/v/landing.blade.php')
            ->and($result)->toContain('/v/page.blade.php', 'page.php');
    });
});

describe('WordPressTemplateHierarchyFilter template include', function (): void {
    it('delegates template_include resolution to the use case', function (): void {
        $resolve = m::mock(ResolveBladeTemplateUseCase::class);
        $resolve->shouldReceive('execute')->once()->with('/t/page.php')->andReturn('/t/resolved.php');

        expect(hierarchyFilter(resolve: $resolve)->resolveTemplateInclude('/t/page.php'))->toBe('/t/resolved.php');
    });

    it('only makes a template includable while template_redirect is running', function (): void {
        $includable = m::mock(MakeTemplateIncludableUseCase::class);
        $includable->shouldReceive('execute')->once()->with('/t/404.blade.php')->andReturn('/c/loader.php');
        $filter = hierarchyFilter(includable: $includable);

        Functions\when('doing_action')->justReturn(false);
        $outside = $filter->includableTemplate('/t/404.blade.php');

        Functions\when('doing_action')->alias(fn (string $hook): bool => $hook === 'template_redirect');
        $inside = $filter->includableTemplate('/t/404.blade.php');

        expect($outside)->toBe('/t/404.blade.php')->and($inside)->toBe('/c/loader.php');
    });
});

describe('WordPressTemplateHierarchyFilter theme templates', function (): void {
    beforeEach(function (): void {
        $this->cache = [];
        Functions\when('wp_cache_get')->alias(fn (string $key) => $this->cache[$key] ?? false);
        Functions\when('wp_cache_set')->alias(function (string $key, $value): bool {
            $this->cache[$key] = $value;

            return true;
        });
        $this->dir = hierarchyViewsDir([
            'landing.blade.php' => "{{-- Template Name: Landing --}}\n<div></div>",
            'sub/product.blade.php' => "{{-- Template Name:  Product page  --}}\n{{-- Template Post Type: product, page --}}",
            'plain.blade.php' => '<div>no header</div>',
            'legacy.php' => "{{-- Template Name: Not Blade --}}",
        ]);
        $this->views = m::mock(ViewFinderInterface::class);
        $this->views->shouldReceive('getPaths')->andReturn([$this->dir, '/does/not/exist']);
    });

    afterEach(function (): void {
        hierarchyRemove($this->dir);
    });

    it('adds the Blade templates declared for the post type to the WordPress ones', function (): void {
        $filter = hierarchyFilter(views: $this->views);

        expect($filter->extendThemeTemplates(['existing.php' => 'Existing'], null, null, 'page'))
            ->toEqual(['existing.php' => 'Existing', 'landing.blade.php' => 'Landing', 'sub/product.blade.php' => 'Product page'])
            ->and($filter->extendThemeTemplates([], null, null, 'product'))
            ->toEqual(['sub/product.blade.php' => 'Product page'])
            ->and($filter->extendThemeTemplates([], null, null, 'post'))->toBe([]);
    });

    it('lists every declared template when no post type is given', function (): void {
        expect(hierarchyFilter(views: $this->views)->extendThemeTemplates([], null, null, ''))
            ->toEqual(['landing.blade.php' => 'Landing', 'sub/product.blade.php' => 'Product page']);
    });

    it('serves a post type from the cache once it has been scanned', function (): void {
        $filter = hierarchyFilter(views: $this->views);
        $filter->extendThemeTemplates([], null, null, 'page');

        expect($this->cache['pollora/theme_templates']['page'])->toEqual(['landing.blade.php' => 'Landing', 'sub/product.blade.php' => 'Product page']);

        $this->cache['pollora/theme_templates']['page'] = ['cached.blade.php' => 'From cache'];

        expect($filter->extendThemeTemplates([], null, null, 'page'))->toEqual(['cached.blade.php' => 'From cache']);
    });

    it('loads the theme text domain when the theme object can', function (): void {
        $theme = new class
        {
            public array $calls = [];

            public function load_textdomain(): bool
            {
                $this->calls[] = 'load_textdomain';

                return true;
            }

            public function get(string $key): string
            {
                $this->calls[] = 'get:'.$key;

                return 'x';
            }
        };

        hierarchyFilter(views: $this->views)->extendThemeTemplates([], $theme, null, 'page');

        expect($theme->calls)->toBe(['load_textdomain', 'get:TextDomain']);
    });
});
