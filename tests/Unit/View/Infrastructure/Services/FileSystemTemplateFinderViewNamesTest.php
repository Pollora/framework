<?php

declare(strict_types=1);

use Illuminate\View\FileViewFinder;
use Pollora\Filesystem\Filesystem;
use Pollora\View\Infrastructure\Services\FileSystemTemplateFinder;

/**
 * The view name of a template file, and the Blade equivalents of PHP candidates.
 * The theme root is always the real path, so nothing here depends on how the
 * temporary directory is spelled.
 */
function finderThemeRoot(): string
{
    $root = sys_get_temp_dir().'/pollora-finder-'.bin2hex(random_bytes(6));
    mkdir($root.'/resources/views/partials', 0777, true);

    return realpath($root);
}

function finderFor(string $root, array $viewPaths): FileSystemTemplateFinder
{
    $viewFinder = Mockery::mock(FileViewFinder::class);
    $viewFinder->shouldReceive('getPaths')->andReturn($viewPaths);

    return new FileSystemTemplateFinder($viewFinder, new Filesystem, $root);
}

beforeEach(function (): void {
    FileSystemTemplateFinder::clearLocateCache();
    $this->root = finderThemeRoot();
});

afterEach(function (): void {
    (new Illuminate\Filesystem\Filesystem)->deleteDirectory($this->root);
    FileSystemTemplateFinder::clearLocateCache();
});

describe('FileSystemTemplateFinder view names', function (): void {
    it('turns a template path into a dotted view name relative to the views directory', function (): void {
        $finder = finderFor($this->root, [$this->root.'/resources/views']);

        expect($finder->getViewNameFromPath($this->root.'/resources/views/partials/card.blade.php'))->toBe('partials.card')
            ->and($finder->getViewNameFromPath($this->root.'/resources/views/single.blade.php'))->toBe('single');
    });

    it('keeps the directory when the file is not under a view path', function (): void {
        $finder = finderFor($this->root, [$this->root.'/resources/views']);

        expect($finder->getViewNameFromPath($this->root.'/templates/landing.php'))->toBe('templates.landing');
    });

    it('returns null when nothing is left of the path', function (): void {
        $finder = finderFor($this->root, []);

        expect($finder->getViewNameFromPath($this->root))->toBeNull();
    });

    it('works on the raw path when no theme path is known', function (): void {
        $finder = finderFor('', []);
        (new ReflectionProperty($finder, 'path'))->setValue($finder, '');

        expect($finder->getViewNameFromPath('/some/where/page.blade.php'))->toBe('some.where.page');
    });
});

describe('FileSystemTemplateFinder Blade equivalents', function (): void {
    it('swaps .php for .blade.php and leaves Blade and other names alone', function (): void {
        $finder = finderFor($this->root, []);

        expect($finder->getBladeTemplates(['single.php', 'page.blade.php', 'readme.txt', 'landing']))
            ->toBe(['single.blade.php', 'page.blade.php', 'readme.txt', 'landing']);
    });

    it('reports a template as existing only when a Blade file is located for it', function (): void {
        file_put_contents($this->root.'/resources/views/single.blade.php', 'x');
        $finder = finderFor($this->root, [$this->root.'/resources/views']);

        expect($finder->exists('single.php'))->toBeTrue();

        FileSystemTemplateFinder::clearLocateCache();

        expect(finderFor($this->root, [$this->root.'/resources/views'])->exists('missing.php'))->toBeFalse();
    });
});
