<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\View\ViewFinderInterface;
use Pollora\Filesystem\Filesystem;
use Pollora\View\Application\UseCases\MakeTemplateIncludableUseCase;
use Pollora\View\Application\UseCases\ResolveBladeTemplateUseCase;
use Pollora\View\Infrastructure\Services\FileSystemTemplateFinder;
use Pollora\View\Infrastructure\Services\WordPressTemplateHierarchyFilter;

beforeEach(function (): void {
    FileSystemTemplateFinder::clearLocateCache();

    $this->viewFinder = Mockery::mock(ViewFinderInterface::class);
    $this->filesystem = Mockery::mock(Filesystem::class);
});

describe('FileSystemTemplateFinder::locate()', function (): void {
    it('caches results for the same template name', function (): void {
        $tempDir = sys_get_temp_dir().'/pollora-test-'.uniqid();
        mkdir($tempDir, 0755, true);
        file_put_contents($tempDir.'/test.blade.php', 'test');

        $this->viewFinder->shouldReceive('getPaths')->once()->andReturn([$tempDir]);
        $this->filesystem->shouldReceive('getRelativePath')->andReturnUsing(
            fn ($base, $path): string => basename($path)
        );

        $finder = new FileSystemTemplateFinder($this->viewFinder, $this->filesystem, $tempDir);

        // First call — should hit filesystem
        $result1 = $finder->locate('test.php');
        // Second call — should use cache (getPaths only called once)
        $result2 = $finder->locate('test.php');

        expect($result1)->toBe($result2);
        expect($result1)->toContain('test.blade.php');

        // Cleanup
        unlink($tempDir.'/test.blade.php');
        rmdir($tempDir);
    });

    it('ranks every Blade candidate above every PHP one, across view paths', function (): void {
        // Mirrors a real theme: the root holds the stub index.php WordPress
        // needs to consider the theme valid, and is registered ahead of
        // resources/views, where the actual Blade templates live.
        $themeRoot = sys_get_temp_dir().'/pollora-theme-'.uniqid();
        $viewsPath = $themeRoot.'/resources/views';
        mkdir($viewsPath, 0755, true);
        file_put_contents($themeRoot.'/index.php', '<?php // Silence is golden...');
        file_put_contents($viewsPath.'/index.blade.php', 'the real template');

        $this->viewFinder->shouldReceive('getPaths')->andReturn([$themeRoot, $viewsPath]);
        $this->filesystem->shouldReceive('getRelativePath')->andReturnUsing(
            fn (string $base, string $path): string => str_replace($base, '', $path)
        );

        $finder = new FileSystemTemplateFinder($this->viewFinder, $this->filesystem, $themeRoot);

        expect($finder->locate('index.php'))->toBe([
            'resources/views/index.blade.php',
            'index.php',
        ]);

        unlink($themeRoot.'/index.php');
        unlink($viewsPath.'/index.blade.php');
        rmdir($viewsPath);
        rmdir($themeRoot.'/resources');
        rmdir($themeRoot);
    });

    it('still finds a classic PHP template when the theme has no Blade equivalent', function (): void {
        $themeRoot = sys_get_temp_dir().'/pollora-theme-'.uniqid();
        mkdir($themeRoot, 0755, true);
        file_put_contents($themeRoot.'/single.php', '<?php // classic template');

        $this->viewFinder->shouldReceive('getPaths')->andReturn([$themeRoot]);
        $this->filesystem->shouldReceive('getRelativePath')->andReturnUsing(
            fn (string $base, string $path): string => str_replace($base, '', $path)
        );

        $finder = new FileSystemTemplateFinder($this->viewFinder, $this->filesystem, $themeRoot);

        expect($finder->locate('single.php'))->toBe(['single.php']);

        unlink($themeRoot.'/single.php');
        rmdir($themeRoot);
    });

    it('returns different results for different templates', function (): void {
        $tempDir = sys_get_temp_dir().'/pollora-test-'.uniqid();
        mkdir($tempDir, 0755, true);
        file_put_contents($tempDir.'/single.blade.php', 'single');
        file_put_contents($tempDir.'/archive.blade.php', 'archive');

        $this->viewFinder->shouldReceive('getPaths')->andReturn([$tempDir]);
        $this->filesystem->shouldReceive('getRelativePath')->andReturnUsing(
            fn ($base, $path): string => basename($path)
        );

        $finder = new FileSystemTemplateFinder($this->viewFinder, $this->filesystem, $tempDir);

        $result1 = $finder->locate('single.php');
        $result2 = $finder->locate('archive.php');

        expect($result1)->not->toBe($result2);
        expect($result1)->toContain('single.blade.php');
        expect($result2)->toContain('archive.blade.php');

        // Cleanup
        unlink($tempDir.'/single.blade.php');
        unlink($tempDir.'/archive.blade.php');
        rmdir($tempDir);
    });

    it('clears cache via clearLocateCache', function (): void {
        $tempDir = sys_get_temp_dir().'/pollora-test-'.uniqid();
        mkdir($tempDir, 0755, true);
        file_put_contents($tempDir.'/page.blade.php', 'page');

        $this->viewFinder->shouldReceive('getPaths')->andReturn([$tempDir]);
        $this->filesystem->shouldReceive('getRelativePath')->andReturnUsing(
            fn ($base, $path): string => basename($path)
        );

        $finder = new FileSystemTemplateFinder($this->viewFinder, $this->filesystem, $tempDir);

        $result1 = $finder->locate('page.php');

        FileSystemTemplateFinder::clearLocateCache();

        // After clearing, getPaths will be called again
        $result2 = $finder->locate('page.php');

        expect($result1)->toBe($result2);

        // Cleanup
        unlink($tempDir.'/page.blade.php');
        rmdir($tempDir);
    });

    it('handles array input by merging results', function (): void {
        $tempDir = sys_get_temp_dir().'/pollora-test-'.uniqid();
        mkdir($tempDir, 0755, true);
        file_put_contents($tempDir.'/single.blade.php', 'single');
        file_put_contents($tempDir.'/page.blade.php', 'page');

        $this->viewFinder->shouldReceive('getPaths')->andReturn([$tempDir]);
        $this->filesystem->shouldReceive('getRelativePath')->andReturnUsing(
            fn ($base, $path): string => basename($path)
        );

        $finder = new FileSystemTemplateFinder($this->viewFinder, $this->filesystem, $tempDir);

        $result = $finder->locate(['single.php', 'page.php']);

        expect($result)->toContain('single.blade.php');
        expect($result)->toContain('page.blade.php');

        // Cleanup
        unlink($tempDir.'/single.blade.php');
        unlink($tempDir.'/page.blade.php');
        rmdir($tempDir);
    });
});

/**
 * WordPress puts the page template slug first in the page hierarchy, without
 * extension: `landing` for a template declared in the theme's
 * config/templates.php. It stands for landing.blade.php (Pollora/pollora#159).
 */
describe('page template slugs', function (): void {
    beforeEach(function (): void {
        $this->themeRoot = sys_get_temp_dir().'/pollora-theme-'.uniqid();
        $this->viewsPath = $this->themeRoot.'/resources/views';
        mkdir($this->viewsPath.'/landing', 0755, true);
        file_put_contents($this->themeRoot.'/index.php', '<?php // Silence is golden...');
        file_put_contents($this->viewsPath.'/page.blade.php', 'page');
        file_put_contents($this->viewsPath.'/landing.blade.php', 'landing');

        $this->viewFinder->shouldReceive('getPaths')->andReturn([$this->themeRoot, $this->viewsPath]);
        $this->filesystem->shouldReceive('getRelativePath')->andReturnUsing(
            fn (string $base, string $path): string => str_replace($base, '', $path)
        );

        $this->finder = new FileSystemTemplateFinder($this->viewFinder, $this->filesystem, $this->themeRoot);
    });

    afterEach(function (): void {
        exec('rm -rf '.escapeshellarg($this->themeRoot));
    });

    it('reads a candidate without extension as a Blade view', function (): void {
        expect($this->finder->locate('landing'))->toBe(['resources/views/landing.blade.php'])
            ->and($this->finder->locate('missing'))->toBe([]);
    });

    it('lets the template declared in config/templates.php outrank page.blade.php', function (): void {
        Functions\when('wp_is_block_theme')->justReturn(false);

        $filter = new WordPressTemplateHierarchyFilter(
            $this->finder,
            Mockery::mock(ResolveBladeTemplateUseCase::class),
            $this->viewFinder,
            Mockery::mock(MakeTemplateIncludableUseCase::class),
        );

        $hierarchy = ['landing', 'page-issue-159.php', 'page-9.php', 'page.php'];

        expect($filter->extendTemplateHierarchy($hierarchy)[0])->toBe('resources/views/landing.blade.php');
    });

    it('keeps the template first for a block theme', function (): void {
        Functions\when('wp_is_block_theme')->justReturn(true);
        Functions\when('current_theme_supports')->justReturn(true);
        Functions\when('get_page_template_slug')->justReturn('landing');

        $filter = new WordPressTemplateHierarchyFilter(
            $this->finder,
            Mockery::mock(ResolveBladeTemplateUseCase::class),
            $this->viewFinder,
            Mockery::mock(MakeTemplateIncludableUseCase::class),
        );

        expect($filter->extendTemplateHierarchy(['landing', 'page.php'])[0])->toBe('resources/views/landing.blade.php');
    });

    it('leaves a file with another extension alone', function (): void {
        file_put_contents($this->viewsPath.'/landing.html', '<!-- block template -->');

        expect($this->finder->locate('landing.html'))->toBe(['resources/views/landing.html']);
    });
});
