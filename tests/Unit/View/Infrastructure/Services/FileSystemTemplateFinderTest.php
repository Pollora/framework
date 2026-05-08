<?php

declare(strict_types=1);

use Illuminate\View\ViewFinderInterface;
use Pollora\Filesystem\Filesystem;
use Pollora\View\Infrastructure\Services\FileSystemTemplateFinder;

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
            fn ($base, $path) => basename($path)
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

    it('returns different results for different templates', function (): void {
        $tempDir = sys_get_temp_dir().'/pollora-test-'.uniqid();
        mkdir($tempDir, 0755, true);
        file_put_contents($tempDir.'/single.blade.php', 'single');
        file_put_contents($tempDir.'/archive.blade.php', 'archive');

        $this->viewFinder->shouldReceive('getPaths')->andReturn([$tempDir]);
        $this->filesystem->shouldReceive('getRelativePath')->andReturnUsing(
            fn ($base, $path) => basename($path)
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
            fn ($base, $path) => basename($path)
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
            fn ($base, $path) => basename($path)
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
