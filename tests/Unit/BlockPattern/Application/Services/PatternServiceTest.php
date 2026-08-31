<?php

declare(strict_types=1);

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
 * Create a throwaway themes root containing the given themes.
 *
 * Each theme gets `resources/views/patterns/<slug>.blade.php` for every slug
 * listed, so the service has a real directory tree to walk.
 *
 * @param  array<string, array<int, string>>  $themes  Theme name => pattern slugs
 */
function makeThemesRoot(array $themes): string
{
    $root = sys_get_temp_dir().'/pollora-patterns-'.bin2hex(random_bytes(6));

    foreach ($themes as $themeName => $slugs) {
        $patternDir = $root.'/'.$themeName.'/resources/views/patterns';
        mkdir($patternDir, 0o777, true);

        foreach ($slugs as $slug) {
            file_put_contents($patternDir.'/'.$slug.'.blade.php', '<!-- wp:paragraph --><p>'.$slug.'</p><!-- /wp:paragraph -->');
        }
    }

    return $root;
}

function removeThemesRoot(string $root): void
{
    if (! is_dir($root)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($root);
}

/**
 * Build a PatternService whose extractor turns any file into a valid pattern
 * named after the file, and whose registrar records what it was handed.
 *
 * @param  array<int, Pattern>  $registered  Collects registered patterns by reference
 */
function makePatternService(ThemeService $themeService, array &$registered): PatternService
{
    $extractor = Mockery::mock(PatternDataExtractorInterface::class);
    $extractor->shouldReceive('extractFromFile')
        ->andReturnUsing(fn (string $file): PatternFileData => new PatternFileData($file, [
            'slug' => 'theme/'.basename($file, '.blade.php'),
            'title' => basename($file, '.blade.php'),
        ]));
    $extractor->shouldReceive('processData')
        ->andReturnUsing(fn (PatternFileData $data): array => $data->getHeaders());
    $extractor->shouldReceive('getContent')
        ->andReturnUsing(fn (string $file): string => '<p>'.basename($file, '.blade.php').'</p>');

    $registrar = Mockery::mock(PatternRegistrarInterface::class);
    $registrar->shouldReceive('registerPattern')
        ->andReturnUsing(function (Pattern $pattern) use (&$registered): void {
            $registered[] = $pattern;
        });

    $config = Mockery::mock(ConfigRepositoryInterface::class);
    $config->shouldReceive('get')->andReturn([]);

    return new PatternService(
        $config,
        $extractor,
        Mockery::mock(PatternCategoryRegistrarInterface::class),
        $registrar,
        $themeService
    );
}

describe('PatternService', function (): void {
    afterEach(function (): void {
        if (isset($this->themesRoot)) {
            removeThemesRoot($this->themesRoot);
        }
    });

    // These tests deliberately leave `wp_get_theme()` undefined: the pattern
    // directory must be resolved from the theme metadata alone. Resolving it
    // through WordPress instead points at `WP_CONTENT_DIR/themes`, which is not
    // where Pollora keeps its themes, and silently registers nothing.

    it('registers patterns from the theme path rather than through WordPress', function (): void {
        $this->themesRoot = makeThemesRoot(['my-theme' => ['hero']]);

        $themeService = Mockery::mock(ThemeService::class);
        $themeService->shouldReceive('theme')->andReturn(new ThemeMetadata('my-theme', $this->themesRoot));
        $themeService->shouldReceive('getParentThemes')->andReturn([]);

        $registered = [];
        makePatternService($themeService, $registered)->registerAll();

        expect($registered)->toHaveCount(1);
        expect($registered[0]->getSlug())->toBe('theme/hero');
    });

    it('registers patterns from the whole theme ancestry, ancestors first', function (): void {
        $this->themesRoot = makeThemesRoot([
            'grandparent' => ['base'],
            'parent' => ['header'],
            'child' => ['hero'],
        ]);

        $themeService = Mockery::mock(ThemeService::class);
        $themeService->shouldReceive('theme')->andReturn(new ThemeMetadata('child', $this->themesRoot));
        $themeService->shouldReceive('getParentThemes')->andReturn([
            new ThemeMetadata('parent', $this->themesRoot),
            new ThemeMetadata('grandparent', $this->themesRoot),
        ]);

        $registered = [];
        makePatternService($themeService, $registered)->registerAll();

        $slugs = array_map(fn (Pattern $pattern): string => $pattern->getSlug(), $registered);

        expect($slugs)->toBe(['theme/base', 'theme/header', 'theme/hero']);
    });

    it('does nothing when no theme is active', function (): void {
        $themeService = Mockery::mock(ThemeService::class);
        $themeService->shouldReceive('theme')->andReturnNull();
        $themeService->shouldReceive('getParentThemes')->andReturn([]);

        $registered = [];
        makePatternService($themeService, $registered)->registerAll();

        expect($registered)->toBeEmpty();
    });

    it('does nothing when the theme has no patterns directory', function (): void {
        $this->themesRoot = makeThemesRoot(['my-theme' => []]);
        removeThemesRoot($this->themesRoot.'/my-theme/resources/views/patterns');

        $themeService = Mockery::mock(ThemeService::class);
        $themeService->shouldReceive('theme')->andReturn(new ThemeMetadata('my-theme', $this->themesRoot));
        $themeService->shouldReceive('getParentThemes')->andReturn([]);

        $registered = [];
        makePatternService($themeService, $registered)->registerAll();

        expect($registered)->toBeEmpty();
    });
});
