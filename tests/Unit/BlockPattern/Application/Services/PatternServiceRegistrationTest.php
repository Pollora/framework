<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Filesystem\Filesystem;
use Pollora\BlockPattern\Application\Services\PatternService;
use Pollora\BlockPattern\Domain\Contracts\PatternCategoryRegistrarInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternDataExtractorInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternRegistrarInterface;
use Pollora\BlockPattern\Domain\Models\Pattern;
use Pollora\BlockPattern\Domain\Models\PatternFileData;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Models\ThemeMetadata;

/**
 * @param  array<string, list<string>>  $themes  Theme name => pattern file names
 */
function contentPatternThemes(array $themes): string
{
    $root = sys_get_temp_dir().'/pollora-content-patterns-'.bin2hex(random_bytes(6));

    foreach ($themes as $theme => $files) {
        $dir = $root.'/'.$theme.'/resources/views/patterns';
        mkdir($dir, 0o777, true);

        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            (new Filesystem)->ensureDirectoryExists(dirname($path));
            file_put_contents($path, 'x');
        }
    }

    return $root;
}

function contentRemoveTree(string $root): void
{
    if (! is_dir($root)) {
        return;
    }

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($root);
}

/**
 * @param  array<string, array<string, string>>  $headers  File basename => headers
 * @param  array<string, string|null>  $contents  File basename => rendered content
 * @param  array<int, Pattern>  $registered
 * @param  array<string, array<string, mixed>>  $categories
 * @param  array<string, mixed>  $configuredCategories
 */
function contentPatternService(ThemeService $themes, array $headers, array $contents, array &$registered, array &$categories = [], array $configuredCategories = []): PatternService
{
    $extractor = Mockery::mock(PatternDataExtractorInterface::class);
    $extractor->shouldReceive('extractFromFile')->andReturnUsing(fn (string $file): PatternFileData => new PatternFileData($file, $headers[basename($file)] ?? []));
    $extractor->shouldReceive('processData')->andReturnUsing(fn (PatternFileData $data): array => $data->getHeaders());
    $extractor->shouldReceive('getContent')->andReturnUsing(fn (string $file): ?string => $contents[basename($file)] ?? null);

    $registrar = Mockery::mock(PatternRegistrarInterface::class);
    $registrar->shouldReceive('registerPattern')->andReturnUsing(function (Pattern $pattern) use (&$registered): void {
        $registered[] = $pattern;
    });

    $categoryRegistrar = Mockery::mock(PatternCategoryRegistrarInterface::class);
    $categoryRegistrar->shouldReceive('registerCategory')->andReturnUsing(function (string $slug, array $attributes) use (&$categories): void {
        $categories[$slug] = $attributes;
    });

    $config = Mockery::mock(ConfigRepositoryInterface::class);
    $config->shouldReceive('get')->with('theme.gutenberg.categories.patterns', [])->andReturn($configuredCategories);

    return new PatternService($config, $extractor, $categoryRegistrar, $registrar, $themes);
}

function contentThemeService(?ThemeMetadata $active, array $parents = []): ThemeService
{
    $service = Mockery::mock(ThemeService::class);
    $service->shouldReceive('theme')->andReturn($active);
    $service->shouldReceive('getParentThemes')->andReturn($parents);

    return $service;
}

describe('PatternService registration', function (): void {
    beforeEach(function (): void {
        $this->root = null;
        $this->registered = [];

        // These tests are about the theme path, not about WordPress. Other test
        // files define wp_get_theme() for the whole process; when it exists, it
        // must hand back an object, and when it does not, it must stay undefined
        // so that the pattern service tests that rely on that keep passing.
        if (function_exists('wp_get_theme')) {
            Functions\when('wp_get_theme')->alias(fn (): object => new stdClass);
        }
    });

    afterEach(function (): void {
        if ($this->root !== null) {
            contentRemoveTree($this->root);
        }
    });

    it('registers the pattern categories declared in the theme configuration', function (): void {
        $categories = [];
        $service = contentPatternService(contentThemeService(null), [], [], $this->registered, $categories, [
            'featured' => ['label' => 'Featured'],
            'heroes' => ['label' => 'Heroes'],
        ]);

        $service->registerAll();

        expect($categories)->toBe(['featured' => ['label' => 'Featured'], 'heroes' => ['label' => 'Heroes']]);
    });

    it('registers the patterns of a theme of the active hierarchy by name', function (): void {
        $this->root = contentPatternThemes(['child' => ['hero.php'], 'parent' => ['header.php']]);
        $themes = contentThemeService(new ThemeMetadata('child', $this->root), [new ThemeMetadata('parent', $this->root)]);
        $service = contentPatternService($themes, [
            'hero.php' => ['slug' => 'c/hero', 'title' => 'Hero'],
            'header.php' => ['slug' => 'p/header', 'title' => 'Header'],
        ], ['hero.php' => '<p>Hero</p>', 'header.php' => '<p>Header</p>'], $this->registered);

        $service->registerPattern('parent');

        expect(array_map(fn (Pattern $p): string => $p->getSlug(), $this->registered))->toBe(['p/header']);
    });

    it('registers the patterns of a theme outside the hierarchy from the same themes directory', function (): void {
        $this->root = contentPatternThemes(['active' => [], 'other' => ['banner.php']]);
        $themes = contentThemeService(new ThemeMetadata('active', $this->root));
        $service = contentPatternService($themes, ['banner.php' => ['slug' => 'o/banner', 'title' => 'Banner']], ['banner.php' => '<p>B</p>'], $this->registered);

        $service->registerPattern('other');

        expect(array_map(fn (Pattern $p): string => $p->getSlug(), $this->registered))->toBe(['o/banner']);
    });

    it('can name a theme of the hierarchy when no theme is active', function (): void {
        $this->root = contentPatternThemes(['parent' => ['header.php']]);
        $themes = contentThemeService(null, [new ThemeMetadata('parent', $this->root)]);
        $service = contentPatternService($themes, ['header.php' => ['slug' => 'p/header', 'title' => 'Header']], ['header.php' => '<p>H</p>'], $this->registered);

        $service->registerPattern('parent');

        expect($this->registered)->toHaveCount(1);
    });

    it('registers nothing for an unknown theme when no theme is active', function (): void {
        $service = contentPatternService(contentThemeService(null), [], [], $this->registered);

        $service->registerPattern('ghost');

        expect($this->registered)->toBe([]);
    });

    it('skips a pattern file without a slug or a title', function (): void {
        $this->root = contentPatternThemes(['child' => ['no-slug.php', 'no-title.php', 'ok.php']]);
        $service = contentPatternService(contentThemeService(new ThemeMetadata('child', $this->root)), [
            'no-slug.php' => ['title' => 'No slug'],
            'no-title.php' => ['slug' => 'c/no-title'],
            'ok.php' => ['slug' => 'c/ok', 'title' => 'Ok'],
        ], ['no-slug.php' => '<p>a</p>', 'no-title.php' => '<p>b</p>', 'ok.php' => '<p>c</p>'], $this->registered);

        $service->registerAll();

        expect(array_map(fn (Pattern $p): string => $p->getSlug(), $this->registered))->toBe(['c/ok']);
    });

    it('skips a pattern whose view renders nothing', function (): void {
        $this->root = contentPatternThemes(['child' => ['missing.php', 'empty.php', 'zero.php', 'ok.php']]);
        $headers = [];
        foreach (['missing', 'empty', 'zero', 'ok'] as $name) {
            $headers[$name.'.php'] = ['slug' => 'c/'.$name, 'title' => ucfirst($name)];
        }

        $service = contentPatternService(contentThemeService(new ThemeMetadata('child', $this->root)), $headers, [
            'empty.php' => '',
            'zero.php' => '0',
            'ok.php' => '<p>c</p>',
        ], $this->registered);

        $service->registerAll();

        expect(array_map(fn (Pattern $p): string => $p->getSlug(), $this->registered))->toBe(['c/ok']);
    });

    it('only looks at PHP files, in the subdirectories too', function (): void {
        $this->root = contentPatternThemes(['child' => ['hero.php', 'readme.txt', 'sections/cta.php']]);
        $service = contentPatternService(contentThemeService(new ThemeMetadata('child', $this->root)), [
            'hero.php' => ['slug' => 'c/hero', 'title' => 'Hero'],
            'readme.txt' => ['slug' => 'c/readme', 'title' => 'Readme'],
            'cta.php' => ['slug' => 'c/cta', 'title' => 'Cta'],
        ], ['hero.php' => '<p>h</p>', 'readme.txt' => '<p>r</p>', 'cta.php' => '<p>c</p>'], $this->registered);

        $service->registerAll();

        $slugs = array_map(fn (Pattern $p): string => $p->getSlug(), $this->registered);
        sort($slugs);

        expect($slugs)->toBe(['c/cta', 'c/hero']);
    });
});
