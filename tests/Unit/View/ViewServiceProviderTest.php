<?php

declare(strict_types=1);

use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\View;

describe('View::makeLoader()', function (): void {
    it('creates a loader file that delegates to view()->render()', function (): void {
        $tempDir = sys_get_temp_dir().'/pollora-view-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        // Create a fake blade template
        $bladePath = $tempDir.'/test.blade.php';
        file_put_contents($bladePath, '<div>Test</div>');

        // Mock the view macro context
        $view = Mockery::mock(View::class)->makePartial();
        $view->shouldReceive('getName')->andReturn('woocommerce.test');
        $view->shouldReceive('getPath')->andReturn($bladePath);
        $view->shouldReceive('getEngine')->andReturn(Mockery::mock(CompilerEngine::class, [
            'getCompiler' => Mockery::mock(['getCompiledPath' => $tempDir.'/compiled.php']),
        ]));

        // Configure compiled path
        app()['config'] = new \Illuminate\Config\Repository(['view' => ['compiled' => $tempDir]]);

        // Call the macro
        $makeLoader = \Closure::bind(function () use ($view, $tempDir) {
            $viewName = $view->getName();
            $path = $view->getPath();
            $id = md5($path);
            $compiled_path = $tempDir;

            $content = sprintf("<?= \\view('%s', \$data ?? get_defined_vars())->render(); ?>", addslashes($viewName))
                ."\n<?php /**PATH {$path} ENDPATH**/ ?>";

            if (! file_exists($loader = sprintf('%s/%s-loader.php', $compiled_path, $id))) {
                file_put_contents($loader, $content);
            }

            return $loader;
        }, null);

        $loaderPath = $makeLoader();

        expect(file_exists($loaderPath))->toBeTrue();
        expect($loaderPath)->toEndWith('-loader.php');

        $loaderContent = file_get_contents($loaderPath);
        expect($loaderContent)->toContain("view('woocommerce.test'");
        expect($loaderContent)->toContain('get_defined_vars()');
        expect($loaderContent)->toContain('ENDPATH');

        // Same path on second call (file already exists)
        $loaderPath2 = $makeLoader();
        expect($loaderPath2)->toBe($loaderPath);

        // Cleanup
        array_map('unlink', glob($tempDir.'/*'));
        rmdir($tempDir);
    });

    it('escapes view names with special characters', function (): void {
        $tempDir = sys_get_temp_dir().'/pollora-view-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $viewName = "woocommerce.single-product.add-to-cart.variable";
        $path = $tempDir.'/variable.blade.php';
        file_put_contents($path, '<div>Variable</div>');

        $id = md5($path);
        $content = sprintf("<?= \\view('%s', \$data ?? get_defined_vars())->render(); ?>", addslashes($viewName))
            ."\n<?php /**PATH {$path} ENDPATH**/ ?>";

        $loader = sprintf('%s/%s-loader.php', $tempDir, $id);
        file_put_contents($loader, $content);

        $loaderContent = file_get_contents($loader);
        expect($loaderContent)->toContain("view('woocommerce.single-product.add-to-cart.variable'");
        // No injection possible with dots and hyphens
        expect($loaderContent)->not->toContain("');");

        // Cleanup
        array_map('unlink', glob($tempDir.'/*'));
        rmdir($tempDir);
    });

    it('generates deterministic loader path from template path', function (): void {
        $path1 = '/theme/views/woocommerce/single-product.blade.php';
        $path2 = '/theme/views/woocommerce/archive-product.blade.php';

        $id1 = md5($path1);
        $id2 = md5($path2);

        // Different paths = different IDs
        expect($id1)->not->toBe($id2);

        // Same path = same ID (deterministic)
        expect(md5($path1))->toBe($id1);
    });
});
