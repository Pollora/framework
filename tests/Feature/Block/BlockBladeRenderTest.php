<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Domain\Contracts\ViteManagerInterface;
use Pollora\Block\Infrastructure\Services\BlockPreview;
use Pollora\Block\Infrastructure\Services\BlockRegistrar;
use Pollora\Hook\Domain\Contract\Filter as HookFilter;

if (! class_exists('WP_Block')) {
    eval('class WP_Block {}');
}

if (! class_exists('WP_REST_Request')) {
    eval('class WP_REST_Request { public function __construct(private string $method = "", private string $route = "") {} public function get_route(): string { return $this->route; } }');
}

/**
 * Register the block in $blockDir and return the render callback handed to WordPress.
 */
function bladeBlockRenderCallback(string $blockDir): ?Closure
{
    $registered = [];

    Brain\Monkey\Functions\when('register_block_type')->alias(function ($dir, $args = []) use (&$registered): true {
        $registered = $args;

        return true;
    });

    $registrar = new class(Mockery::mock(AssetManager::class), Mockery::mock(HookFilter::class)->shouldIgnoreMissing()) extends BlockRegistrar
    {
        protected function getBlocksViteManager(string $parentContainerName): ?ViteManagerInterface
        {
            return Mockery::mock(ViteManagerInterface::class)->shouldIgnoreMissing();
        }
    };

    $registrar->registerBlock($blockDir, 'theme');

    return $registered['render_callback'] ?? null;
}

beforeEach(function (): void {
    $this->themeDir = sys_get_temp_dir().'/pollora-blade-block-'.uniqid();
    $this->blockDir = $this->themeDir.'/resources/views/blocks/card';
    mkdir($this->blockDir, 0755, true);
    mkdir($this->themeDir.'/resources/views/components', 0755, true);

    file_put_contents($this->blockDir.'/block.json', json_encode([
        'name' => 'test/card',
        'render' => 'file:./render.blade.php',
    ]));
});

afterEach(function (): void {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->themeDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }

    rmdir($this->themeDir);
});

describe('Blade block rendering', function (): void {
    it('renders render.blade.php with escaped attributes, content and block', function (): void {
        file_put_contents(
            $this->blockDir.'/render.blade.php',
            '<div>{{ $attributes[\'title\'] }}|{!! $content !!}|{{ $block::class }}</div>'
        );

        $render = bladeBlockRenderCallback($this->blockDir);

        expect($render(['title' => '<b>Hi</b>'], '<em>inner</em>', new WP_Block))
            ->toBe('<div>&lt;b&gt;Hi&lt;/b&gt;|<em>inner</em>|WP_Block</div>');
    });

    it('keeps the block $attributes after an anonymous component', function (): void {
        Blade::anonymousComponentPath($this->themeDir.'/resources/views/components');

        file_put_contents(
            $this->themeDir.'/resources/views/components/badge.blade.php',
            '<span {{ $attributes->merge([\'class\' => \'badge\']) }}>{{ $slot }}</span>'
        );
        file_put_contents(
            $this->blockDir.'/render.blade.php',
            '<x-badge class="new">{{ $attributes[\'label\'] }}</x-badge>|{{ $attributes[\'label\'] }}'
        );

        $render = bladeBlockRenderCallback($this->blockDir);

        expect(trim($render(['label' => 'Sale'], '', new WP_Block)))
            ->toBe('<span class="badge new">Sale</span>|Sale');
    });

    it('puts the inner blocks in place of <InnerBlocks /> on the page', function (): void {
        file_put_contents(
            $this->blockDir.'/render.blade.php',
            '<section><h2>{{ $attributes[\'title\'] }}</h2><InnerBlocks class="card__body" allowedBlocks="{{ json_encode([\'core/paragraph\']) }}" /></section>'
        );

        $render = bladeBlockRenderCallback($this->blockDir);

        expect($render(['title' => 'Card'], '<p>Inner</p>', new WP_Block))
            ->toBe('<section><h2>Card</h2><div class="card__body"><p>Inner</p></div></section>');
    });

    it('keeps <InnerBlocks /> and says so to the template in the editor preview', function (): void {
        file_put_contents(
            $this->blockDir.'/render.blade.php',
            "<section>@if (\$isPreview) preview @endif\n<InnerBlocks /></section>"
        );

        $render = bladeBlockRenderCallback($this->blockDir);
        $this->app->singleton(BlockPreview::class);
        $this->app->make(BlockPreview::class)->start(null, null, new WP_REST_Request('POST', '/wp/v2/block-renderer/test/card'));

        expect($render([], '', new WP_Block))->toBe('<section> preview <InnerBlocks /></section>');
    });

    it('puts the inner blocks in place of <InnerBlocks /> in a PHP render file too', function (): void {
        file_put_contents($this->blockDir.'/block.json', json_encode(['name' => 'test/card', 'render' => 'file:./render.php']));
        file_put_contents($this->blockDir.'/render.php', '<section><?= $isPreview ? "preview" : "page" ?><InnerBlocks /></section>');

        $render = bladeBlockRenderCallback($this->blockDir);

        expect($render([], '<p>Inner</p>', new WP_Block))
            ->toBe('<section>page<div class="pollora-inner-blocks"><p>Inner</p></div></section>');
    });
});
