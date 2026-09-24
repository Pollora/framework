<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Domain\Contracts\ViteManagerInterface;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;
use Pollora\Asset\Infrastructure\Services\ViteManager;
use Pollora\Block\Infrastructure\Services\BlockRegistrar;
use Pollora\Hook\Domain\Contract\Filter as HookFilter;
use Psr\Log\AbstractLogger;

/**
 * Testable subclass that injects a mock ViteManager.
 */
/**
 * Logger keeping every record for assertions.
 */
class RecordingBlockLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string}> */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message];
    }

    /**
     * @return list<string>
     */
    public function messages(string $level): array
    {
        return array_values(array_map(
            fn (array $record): string => $record['message'],
            array_filter($this->records, fn (array $record): bool => $record['level'] === $level),
        ));
    }
}

if (! class_exists('WP_Block')) {
    eval('class WP_Block {}');
}

class TestableBlockRegistrar extends BlockRegistrar
{
    public ?ViteManagerInterface $mockViteManager = null;

    /** @var list<string> Block names WordPress already holds */
    public array $alreadyRegistered = [];

    protected function isBlockRegistered(string $blockName): bool
    {
        return in_array($blockName, $this->alreadyRegistered, true);
    }

    protected function getBlocksViteManager(string $parentContainerName): ?ViteManagerInterface
    {
        return $this->mockViteManager;
    }
}

beforeEach(function (): void {
    $this->themeDir = sys_get_temp_dir().'/pollora-block-test-'.uniqid();
    $this->tempDir = $this->themeDir.'/resources/views/blocks';
    mkdir($this->tempDir.'/hero', 0755, true);
    mkdir($this->tempDir.'/card', 0755, true);
    file_put_contents($this->themeDir.'/vite.config.js', 'export default {};');

    $app = Mockery::mock(Container::class)->makePartial();
    $app->shouldReceive('publicPath')->andReturnUsing(fn ($path = ''): string => sys_get_temp_dir().($path ? '/'.$path : ''));
    $app->instance('app', $app);
    Container::setInstance($app);
    Facade::setFacadeApplication($app);
    $this->logger = new RecordingBlockLogger;
    $app->instance('log', $this->logger);

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
        new RecursiveDirectoryIterator($this->themeDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }

    rmdir($this->themeDir);
});

function createMockVite(bool $isHot = false, string $buildDirectory = 'build/pollora-block-test-none'): ViteManagerInterface
{
    $vite = Mockery::mock(ViteManagerInterface::class);
    $vite->shouldReceive('isRunningHot')->andReturn($isHot);
    $vite->shouldReceive('container')->andReturn(new AssetContainer('theme.blocks', [
        'build_directory' => $buildDirectory,
        'manifest_path' => 'manifest.json',
    ]));
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
    $registrar = new TestableBlockRegistrar($assetManager, Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
    $registrar->mockViteManager = createMockVite();

    return $registrar;
}

describe('BlockRegistrar', function (): void {

    it('scans directory and registers only blocks with block.json', function (): void {
        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'title' => 'Hero',
        ]));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
        $registrar->mockViteManager = createMockVite();
        $registrar->registerDirectory($this->tempDir, 'theme');

        expect($this->registeredBlocks)->toHaveCount(1);
        expect($this->registeredBlocks[0]['dir'])->toBe($this->tempDir.'/hero');
    });

    it('skips a block WordPress already holds, without registering its assets again', function (): void {
        // The framework registers every module's blocks by convention; a
        // BlocksServiceProvider kept from an earlier release registers them a
        // second time, which WordPress would reject with a notice.
        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'editorScript' => 'file:./index.jsx',
        ]));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
        $registrar->mockViteManager = createMockVite();
        $registrar->alreadyRegistered = ['test/hero'];
        $registrar->registerDirectory($this->tempDir, 'theme');

        expect($this->registeredBlocks)->toBeEmpty()
            ->and($this->registeredScripts)->toBeEmpty();
    });

    it('skips non-existent directories gracefully', function (): void {
        $registrar = new BlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
        $registrar->registerDirectory('/nonexistent/path', 'theme');

        expect($this->registeredBlocks)->toBeEmpty();
    });

    it('returns early when getBlocksViteManager returns null', function (): void {
        file_put_contents($this->tempDir.'/hero/block.json', json_encode(['name' => 'test/hero']));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
        $registrar->mockViteManager = null;
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredBlocks)->toBeEmpty();
    });

    it('pre-registers editor script with Vite-resolved URL', function (): void {
        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'editorScript' => 'file:./index.jsx',
        ]));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
        $registrar->mockViteManager = createMockVite();
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredScripts)->toHaveKey('test-hero-editor-script');
        expect($this->registeredScripts['test-hero-editor-script']['src'])
            ->toContain('index-abc123.js');
        expect($this->registeredScripts['test-hero-editor-script']['deps'])
            ->toBe(['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n']);
    });

    it('adds the WordPress scripts the build recorded in editor.deps.json to the editor script', function (): void {
        // @roots/vite-plugin externalises every @wordpress/* import and lists
        // the matching script handles in editor.deps.json. Without them, a
        // block importing @wordpress/server-side-render depends on a script
        // WordPress may not load.
        $buildDirectory = 'build/pollora-block-deps-'.uniqid();
        $public = sys_get_temp_dir().'/'.$buildDirectory;
        mkdir($public.'/assets', 0755, true);
        file_put_contents($public.'/manifest.json', json_encode([
            'editor.deps.json' => ['file' => 'assets/editor.deps-abc123.json'],
        ]));
        file_put_contents($public.'/assets/editor.deps-abc123.json', json_encode(['wp-blocks', 'wp-server-side-render', 'wp-components']));

        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'editorScript' => 'file:./index.jsx',
        ]));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
        $registrar->mockViteManager = createMockVite(buildDirectory: $buildDirectory);
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        unlink($public.'/assets/editor.deps-abc123.json');
        rmdir($public.'/assets');
        unlink($public.'/manifest.json');
        rmdir($public);

        expect($this->registeredScripts['test-hero-editor-script']['deps'])
            ->toBe(['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render', 'wp-components']);
    });

    it('keeps the default editor script dependencies when the build recorded none', function (): void {
        // In dev mode (HMR) there is no build, hence no editor.deps.json
        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'editorScript' => 'file:./index.jsx',
        ]));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
        $registrar->mockViteManager = createMockVite(isHot: true);
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredScripts['test-hero-editor-script']['deps'])
            ->toBe(['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n']);
    });

    it('pre-registers styles with Vite-resolved URL', function (): void {
        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'style' => 'file:./style.css',
            'editorStyle' => 'file:./editor.css',
        ]));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
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

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
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

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
        $registrar->mockViteManager = createMockVite(isHot: true);
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredScripts['test-hero-editor-script']['src'])
            ->toBe('http://localhost:5173/resources/views/blocks/hero/index.jsx');
    });

    it('handles dynamic blocks with render.php', function (): void {
        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'render' => 'file:./render.php',
        ]));
        file_put_contents($this->tempDir.'/hero/render.php', '<?php echo "hello"; ?>');

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
        $registrar->mockViteManager = createMockVite();
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredBlocks)->toHaveCount(1);
        expect($this->registeredBlocks[0]['args'])->toHaveKey('render_callback');
        expect($this->registeredBlocks[0]['args']['render_callback'])->toBeCallable();
    });

    it('leaves a classic React block to render the markup it saved', function (): void {
        // save.jsx and no `render` in block.json is the standard Gutenberg
        // shape: the markup save() produced is stored in post_content, and
        // WordPress serves it. A render_callback would override that markup —
        // with nothing, since there is no render file to call. So the callback
        // must be absent, not null: null is what a *declared* render file that
        // cannot be used resolves to, and it means something else.
        file_put_contents($this->tempDir.'/hero/block.json', json_encode([
            'name' => 'test/hero',
            'editorScript' => 'file:./index.jsx',
            'editorStyle' => 'file:./editor.css',
            'style' => 'file:./style.css',
        ]));

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
        $registrar->mockViteManager = createMockVite();
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredBlocks)->toHaveCount(1)
            ->and($this->registeredBlocks[0]['args'])->not->toHaveKey('render_callback')
            ->and($this->registeredScripts)->toHaveKey('test-hero-editor-script')
            ->and($this->registeredStyles)->toHaveKey('test-hero-editor-style')
            ->and($this->registeredStyles)->toHaveKey('test-hero-style');
    });

    it('builds handles matching WP generate_block_asset_handle format', function (): void {
        $registrar = new BlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());

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

        $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
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
        $registrar = new TestableBlockRegistrar($assetManager, Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
        $registrar->mockViteManager = createMockVite();
        $registrar->registerBlock($this->tempDir.'/hero', 'theme');

        // The real BlockRegistrar would create the container, but TestableBlockRegistrar skips it.
        // Test the container creation logic directly:
        expect($parentContainer->getBasePath())->toBe('resources/assets/');

        $method = new ReflectionMethod(BlockRegistrar::class, 'getBlocksViteManager');

        // Use a fresh (non-testable) instance to verify addContainer is called
        $realRegistrar = new BlockRegistrar($assetManager, Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
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

function writeBlock(string $blockDir, array $metadata, array $files = []): void
{
    if (! is_dir($blockDir)) {
        mkdir($blockDir, 0755, true);
    }

    file_put_contents($blockDir.'/block.json', json_encode($metadata));

    foreach ($files as $name => $content) {
        file_put_contents($blockDir.'/'.$name, $content);
    }
}

function blockRegistrar(bool $isHot = true): TestableBlockRegistrar
{
    $registrar = new TestableBlockRegistrar(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing());
    $registrar->mockViteManager = createMockVite(isHot: $isHot);

    return $registrar;
}

describe('BlockRegistrar entry points', function (): void {
    it('resolves assets of a resources/views/blocks block against the Vite root', function (): void {
        writeBlock($this->tempDir.'/hero', ['name' => 'test/hero', 'editorScript' => 'file:./index.jsx', 'style' => 'file:./style.css']);

        blockRegistrar()->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredScripts['test-hero-editor-script']['src'])->toBe('http://localhost:5173/resources/views/blocks/hero/index.jsx')
            ->and($this->registeredStyles['test-hero-style']['src'])->toBe('http://localhost:5173/resources/views/blocks/hero/style.css');
    });

    it('keeps the entry point of a block still in resources/blocks', function (): void {
        writeBlock($this->themeDir.'/resources/blocks/hero', ['name' => 'test/hero', 'editorScript' => 'file:./index.jsx']);

        blockRegistrar()->registerBlock($this->themeDir.'/resources/blocks/hero', 'theme');

        expect($this->registeredScripts['test-hero-editor-script']['src'])->toBe('http://localhost:5173/resources/blocks/hero/index.jsx');
    });

    it('resolves the same entry point from an explicit basePath, a vite config or the resources folder', function (): void {
        writeBlock($this->tempDir.'/hero', ['name' => 'test/hero', 'editorScript' => 'file:index.jsx']);

        blockRegistrar()->registerBlock($this->tempDir.'/hero', 'theme');
        $fromViteConfig = $this->registeredScripts['test-hero-editor-script']['src'];

        unlink($this->themeDir.'/vite.config.js');

        blockRegistrar()->registerBlock($this->tempDir.'/hero', 'theme');
        $fromResourcesFolder = $this->registeredScripts['test-hero-editor-script']['src'];

        blockRegistrar()->registerBlock($this->tempDir.'/hero', 'theme', basePath: $this->themeDir);
        $fromBasePath = $this->registeredScripts['test-hero-editor-script']['src'];

        expect($fromViteConfig)->toBe('http://localhost:5173/resources/views/blocks/hero/index.jsx')
            ->and($fromResourcesFolder)->toBe($fromViteConfig)
            ->and($fromBasePath)->toBe($fromViteConfig);
    });

    it('skips an asset outside the Vite root', function (): void {
        writeBlock($this->tempDir.'/hero', ['name' => 'test/hero', 'editorScript' => 'file:../../../../../outside.js']);

        blockRegistrar()->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredScripts)->toBeEmpty()
            ->and($this->logger->messages('warning'))->toHaveCount(1)
            ->and($this->registeredBlocks)->toHaveCount(1);
    });

    it('skips assets when no Vite root can be found', function (): void {
        $blockDir = $this->themeDir.'/elsewhere/hero';
        unlink($this->themeDir.'/vite.config.js');
        writeBlock($blockDir, ['name' => 'test/hero', 'editorScript' => 'file:./index.jsx']);

        blockRegistrar()->registerBlock($blockDir, 'theme');

        expect($this->registeredScripts)->toBeEmpty()
            ->and($this->logger->messages('warning')[0])->toContain('no Vite project root found');
    });
});

describe('BlockRegistrar render files', function (): void {
    it('renders a PHP render file with the block variables', function (): void {
        writeBlock($this->tempDir.'/hero', ['name' => 'test/hero', 'render' => 'file:./render.php'], [
            'render.php' => '<p><?= $attributes["title"] ?>|<?= $content ?></p>',
        ]);

        blockRegistrar()->registerBlock($this->tempDir.'/hero', 'theme');
        $callback = $this->registeredBlocks[0]['args']['render_callback'];

        expect($callback(['title' => 'Hello'], 'inner', new WP_Block))->toBe('<p>Hello|inner</p>');
    });

    it('disables rendering of a render file outside the block directory', function (): void {
        file_put_contents($this->tempDir.'/evil.php', '<?php echo "pwned";');
        writeBlock($this->tempDir.'/hero', ['name' => 'test/hero', 'render' => 'file:../evil.php']);

        blockRegistrar()->registerBlock($this->tempDir.'/hero', 'theme');

        // null, not absent: WordPress would otherwise require the file itself
        expect($this->registeredBlocks[0]['args'])->toHaveKey('render_callback')
            ->and($this->registeredBlocks[0]['args']['render_callback'])->toBeNull()
            ->and($this->logger->messages('warning'))->toHaveCount(1);
    });

    it('disables rendering of a missing render file', function (): void {
        writeBlock($this->tempDir.'/hero', ['name' => 'test/hero', 'render' => 'file:./render.blade.php']);

        blockRegistrar()->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredBlocks[0]['args']['render_callback'])->toBeNull();
    });

    it('registers a Blade render callback for a .blade.php render file', function (): void {
        writeBlock($this->tempDir.'/hero', ['name' => 'test/hero', 'render' => 'file:./render.blade.php'], [
            'render.blade.php' => '<p>{{ $attributes["title"] }}</p>',
        ]);

        blockRegistrar()->registerBlock($this->tempDir.'/hero', 'theme');

        expect($this->registeredBlocks[0]['args']['render_callback'])->toBeInstanceOf(Closure::class);
    });
});

describe('BlockRegistrar discovery', function (): void {
    it('registers blocks left in resources/blocks and reports the deprecation once', function (): void {
        rmdir($this->tempDir.'/hero');
        rmdir($this->tempDir.'/card');
        rmdir($this->tempDir);
        rmdir($this->themeDir.'/resources/views');
        writeBlock($this->themeDir.'/resources/blocks/legacy-only', ['name' => 'test/legacy-only']);

        blockRegistrar()->registerDirectory($this->themeDir.'/resources/views/blocks', 'theme');
        blockRegistrar()->registerDirectory($this->themeDir.'/resources/views/blocks', 'theme');

        expect($this->registeredBlocks)->toHaveCount(2)
            ->and($this->registeredBlocks[0]['dir'])->toBe($this->themeDir.'/resources/blocks/legacy-only')
            ->and($this->logger->messages('notice'))->toHaveCount(1)
            ->and($this->logger->messages('notice')[0])->toContain('resources/views/blocks');
    });

    it('scans both locations from an old provider pointing at resources/blocks', function (): void {
        writeBlock($this->tempDir.'/hero', ['name' => 'test/hero']);
        writeBlock($this->themeDir.'/resources/blocks/card', ['name' => 'test/card']);

        blockRegistrar()->registerDirectory($this->themeDir.'/resources/blocks', 'theme');

        expect(array_column($this->registeredBlocks, 'dir'))->toBe([
            $this->tempDir.'/hero',
            $this->themeDir.'/resources/blocks/card',
        ]);
    });

    it('prefers resources/views/blocks when a block exists in both locations', function (): void {
        writeBlock($this->tempDir.'/hero', ['name' => 'test/hero']);
        writeBlock($this->themeDir.'/resources/blocks/hero', ['name' => 'test/hero']);

        blockRegistrar()->registerDirectory($this->tempDir, 'theme');

        expect($this->registeredBlocks)->toHaveCount(1)
            ->and($this->registeredBlocks[0]['dir'])->toBe($this->tempDir.'/hero')
            ->and($this->logger->messages('warning'))->toHaveCount(1)
            ->and($this->logger->messages('warning')[0])->toContain('test/hero');
    });

    it('scans a directory outside the theme conventions as is', function (): void {
        writeBlock($this->themeDir.'/custom/hero', ['name' => 'test/hero']);

        blockRegistrar()->registerDirectory($this->themeDir.'/custom', 'theme');

        expect($this->registeredBlocks)->toHaveCount(1)
            ->and($this->logger->messages('notice'))->toBeEmpty();
    });
});
