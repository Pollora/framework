<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Domain\Contracts\ViteManagerInterface;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;
use Pollora\Asset\Infrastructure\Services\ViteManager;
use Pollora\Block\Infrastructure\Services\BlockRegistrar;
use Psr\Log\NullLogger;

/**
 * Testable subclass that injects a mock ViteManager.
 */
class TestableBlockRegistrar extends BlockRegistrar
{
    public ?ViteManagerInterface $mockViteManager = null;

    protected function getBlocksViteManager(string $parentContainerName): ?ViteManagerInterface
    {
        return $this->mockViteManager;
    }
}

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/pollora-block-test-'.uniqid();
    mkdir($this->tempDir.'/hero', 0755, true);
    mkdir($this->tempDir.'/card', 0755, true);

    $app = Mockery::mock(Container::class)->makePartial();
    $app->shouldReceive('publicPath')->andReturnUsing(fn ($path = ''): string => sys_get_temp_dir().($path ? '/'.$path : ''));
    $app->instance('app', $app);
    Container::setInstance($app);
    Facade::setFacadeApplication($app);
    $app->instance('log', new NullLogger);

    \Brain\Monkey\Functions\when('register_block_type')->alias(function ($dir, $args = []): true {
        $this->registeredBlocks[] = ['dir' => $dir, 'args' => $args];

        return true;
    });
    \Brain\Monkey\Functions\when('wp_register_script')->alias(function ($handle, $src, $deps = [], $ver = null, $inFooter = false): true {
        $this->registeredScripts[$handle] = ['src' => $src, 'deps' => $deps];

        return true;
    });
    \Brain\Monkey\Functions\when('wp_register_style')->alias(function ($handle, $src, $deps = [], $ver = null): true {
        $this->registeredStyles[$handle] = ['src' => $src, 'deps' => $deps];

        return true;
    });

    $this->registeredBlocks = [];
    $this->registeredScripts = [];
    $this->registeredStyles = [];
});

afterEach(function (): void {
    Facade::clearResolvedInstances();
    Container::setInstance(new Container);

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }

    rmdir($this->tempDir);
});

function createMockVite(bool $isHot = false): ViteManagerInterface
{
    $vite = Mockery::mock(ViteManagerInterface::class);
    $vite->shouldReceive('isRunningHot')->andReturn($isHot);
    $vite->shouldReceive('asset')->andReturnUsing(fn ($path): string => 'http://localhost:5173/'.$path);
    $vite->shouldReceive('getAssetUrls')->andReturnUsing(function ($entrypoints): array {
        $js = [];
        $css = [];
        foreach ($entrypoints as $ep) {
            $ext = pathinfo($ep, PATHINFO_EXTENSION);
            if (in_array($ext, ['jsx', 'tsx', 'ts', 'js'], true)) {
                $js[] = 'https://example.com/build/assets/'.basename($ep, '.'.$ext).'-abc123.js';
            }

            if ($ext === 'css') {
                $css[] = 'https://example.com/build/assets/'.basename($ep, '.css').'-abc123.css';
            }
        }

        return ['js' => $js, 'css' => $css];
    });

    return $vite;
}

function createTestableRegistrar(array &$scripts, array &$styles, array &$blocks): TestableBlockRegistrar
{
    $assetManager = Mockery::mock(AssetManager::class);
    $registrar = new TestableBlockRegistrar($assetManager);
    $registrar->mockViteManager = createMockVite();

    return $registrar;
}

describe('BlockRegistrar', function (): void {

    it('scans directory and registers only blocks with block.json', function (): void {
        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'title' => 'Hero',
        ]));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class));
        $registrar->mockViteManager = createMockVite();
        $registrar->registerDirectory($this->tempDir, 'theme');

        expect($this->registeredBlocks)->toHaveCount(1);
        expect($this->registeredBlocks[0]['dir'])->toBe($this->tempDir.'/hero');
    });

    it('skips non-existent directories gracefully', function (): void {
        $registrar = new BlockRegistrar(Mockery::mock(AssetManager::class));
        $registrar->registerDirectory('/nonexistent/path', 'theme');

        expect($this->registeredBlocks)->toBeEmpty();
    });

    it('returns early when getBlocksViteManager returns null', function (): void {
        file_put_contents($this->tempDir.'/hero/block.json', json_encode(['name' => 'test/hero']));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class));
        $registrar->mockViteManager = null;
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredBlocks)->toBeEmpty();
    });

    it('pre-registers editor script with Vite-resolved URL', function (): void {
        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'editorScript' => 'file:./index.jsx',
        ]));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class));
        $registrar->mockViteManager = createMockVite();
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredScripts)->toHaveKey('test-hero-editor-script');
        expect($this->registeredScripts['test-hero-editor-script']['src'])
            ->toContain('index-abc123.js');
        expect($this->registeredScripts['test-hero-editor-script']['deps'])
            ->toBe(['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n']);
    });

    it('pre-registers styles with Vite-resolved URL', function (): void {
        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'style' => 'file:./style.css',
            'editorStyle' => 'file:./editor.css',
        ]));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class));
        $registrar->mockViteManager = createMockVite();
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredStyles)->toHaveKey('test-hero-style');
        expect($this->registeredStyles)->toHaveKey('test-hero-editor-style');
    });

    it('registers all asset types for a full block', function (): void {
        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'editorScript' => 'file:./index.jsx',
            'editorStyle' => 'file:./editor.css',
            'style' => 'file:./style.css',
            'viewScript' => 'file:./view.js',
        ]));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class));
        $registrar->mockViteManager = createMockVite();
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredScripts)->toHaveKey('test-hero-editor-script');
        expect($this->registeredScripts)->toHaveKey('test-hero-view-script');
        expect($this->registeredStyles)->toHaveKey('test-hero-editor-style');
        expect($this->registeredStyles)->toHaveKey('test-hero-style');
        expect($this->registeredBlocks)->toHaveCount(1);
    });

    it('registers scripts with HMR URLs when Vite is running hot', function (): void {
        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'editorScript' => 'file:./index.jsx',
        ]));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class));
        $registrar->mockViteManager = createMockVite(isHot: true);
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredScripts['test-hero-editor-script']['src'])
            ->toBe('http://localhost:5173/resources/blocks/hero/index.jsx');
    });

    it('handles dynamic blocks with render.php', function (): void {
        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'render' => 'file:./render.php',
        ]));
        file_put_contents($this->tempDir.'/hero/render.php', '<?php echo "hello"; ?>');

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class));
        $registrar->mockViteManager = createMockVite();
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredBlocks)->toHaveCount(1);
        expect($this->registeredBlocks[0]['args'])->toHaveKey('render_callback');
        expect($this->registeredBlocks[0]['args']['render_callback'])->toBeCallable();
    });

    it('builds handles matching WP generate_block_asset_handle format', function (): void {
        $registrar = new BlockRegistrar(Mockery::mock(AssetManager::class));

        $method = new ReflectionMethod($registrar, 'buildHandle');

        expect($method->invoke($registrar, 'acme/hero-banner', 'editorScript'))
            ->toBe('acme-hero-banner-editor-script');
        expect($method->invoke($registrar, 'acme/hero-banner', 'editorStyle'))
            ->toBe('acme-hero-banner-editor-style');
        expect($method->invoke($registrar, 'acme/hero-banner', 'viewScript'))
            ->toBe('acme-hero-banner-view-script');
    });

    it('ignores fields without file:// prefix', function (): void {
        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'editorScript' => 'my-already-registered-handle',
        ]));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class));
        $registrar->mockViteManager = createMockVite();
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredScripts)->toBeEmpty();
        expect($this->registeredBlocks)->toHaveCount(1);
    });

    it('creates blocks container with empty basePath from parent', function (): void {
        file_put_contents($this->tempDir.'/hero/block.json', json_encode(['name' => 'test/hero']));

        $addedContainers = [];
        $parentContainer = new AssetContainer('theme', [
            'hot_file' => '/tmp/test.hot',
            'build_directory' => 'build/theme/my-theme',
            'manifest_path' => 'manifest.json',
            'base_path' => 'resources/assets/',
        ]);

        $assetManager = Mockery::mock(AssetManager::class);
        $assetManager->shouldReceive('getContainer')->with('theme.blocks')->andReturn(null);
        $assetManager->shouldReceive('getContainer')->with('theme')->andReturn($parentContainer);
        $assetManager->shouldReceive('addContainer')->andReturnUsing(
            function ($name, $config) use (&$addedContainers): void {
                $addedContainers[$name] = $config;
            }
        );

        // Use Testable to avoid real ViteManager instantiation
        $registrar = new TestableBlockRegistrar($assetManager);
        $registrar->mockViteManager = createMockVite();
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        // The real BlockRegistrar would create the container, but TestableBlockRegistrar skips it.
        // Test the container creation logic directly:
        expect($parentContainer->getBasePath())->toBe('resources/assets/');

        $method = new ReflectionMethod(BlockRegistrar::class, 'getBlocksViteManager');

        // Use a fresh (non-testable) instance to verify addContainer is called
        $realRegistrar = new BlockRegistrar($assetManager);
        // This will fail at ViteManager instantiation, but addContainer should have been called
        try {
            $method->invoke($realRegistrar, 'theme');
        } catch (Throwable) {
            // Expected — ViteManager needs Vite facade
        }

        expect($addedContainers)->toHaveKey('theme.blocks');
        expect($addedContainers['theme.blocks']['base_path'])->toBe('');
        expect($addedContainers['theme.blocks']['build_directory'])->toBe('build/theme/my-theme');
    });
});
