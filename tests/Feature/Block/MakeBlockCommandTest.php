<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Pollora\Block\UI\Console\MakeBlockCommand;

const MAKE_BLOCK_VITE_CONFIG = <<<'JS'
import { defineConfig } from 'vite';
import laravel, { refreshPaths } from 'laravel-vite-plugin';
import path from 'path';

const themeName = 'test-theme';

const getThemeConfig = () => ({
    input: ["./resources/assets/app.js"],
    refresh: [
        ...refreshPaths,
        'themes/'+themeName+'/resources/views/**',
    ],
});

export default defineConfig({
    plugins: [
        laravel(getThemeConfig()),
    ],
});
JS;

/**
 * vite.config.js as generated before v13.32: index scripts only, from resources/blocks.
 */
const MAKE_BLOCK_LEGACY_VITE_CONFIG = <<<'JS'
import { defineConfig } from 'vite';
import laravel, { refreshPaths } from 'laravel-vite-plugin';
import { wordpressPlugin } from '@roots/vite-plugin';
import { globSync } from 'glob';
import path from 'path';

const blockEntries = globSync('./resources/blocks/*/index.{js,jsx,ts,tsx}')
    .reduce((acc, file) => {
        const slug = path.basename(path.dirname(file));
        acc[`blocks/${slug}`] = file;
        return acc;
    }, {});
const hasBlocks = Object.keys(blockEntries).length > 0;

export default defineConfig({
    plugins: [
        laravel({
            input: ["./resources/assets/app.js", ...Object.values(blockEntries)],
            refresh: [...refreshPaths, 'resources/blocks/**'],
        }),
        ...(hasBlocks ? [wordpressPlugin()] : []),
    ],
});
JS;

/**
 * Assert that a generated vite.config.js parses as an ES module.
 */
function expectValidViteConfig(string $viteConfigPath): void
{
    $node = trim((string) shell_exec('command -v node'));

    if ($node === '') {
        return;
    }

    $module = sys_get_temp_dir().'/pollora-vite-config-'.uniqid().'.mjs';
    copy($viteConfigPath, $module);
    exec(escapeshellarg($node).' --check '.escapeshellarg($module).' 2>&1', $output, $exitCode);
    unlink($module);

    expect($exitCode)->toBe(0, implode("\n", $output));
}

beforeEach(function (): void {
    $this->themesDir = sys_get_temp_dir().'/pollora-make-block-'.uniqid();
    $this->themeDir = $this->themesDir.'/test-theme';
    mkdir($this->themeDir.'/resources/assets', 0755, true);

    file_put_contents($this->themeDir.'/vite.config.js', MAKE_BLOCK_VITE_CONFIG);
    file_put_contents($this->themeDir.'/package.json', json_encode(['name' => 'test-theme', 'devDependencies' => ['vite' => '^7.0.0']]));

    config(['theme.path' => $this->themesDir]);
    $this->app->make(Kernel::class)->registerCommand($this->app->make(MakeBlockCommand::class));
});

afterEach(function (): void {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->themesDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }

    rmdir($this->themesDir);
});

describe('pollora:make:block', function (): void {
    it('creates a Blade-rendered dynamic block in resources/views/blocks', function (): void {
        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'test-theme', '--title' => "Owner's Hero"])
            ->assertSuccessful();

        $blockDir = $this->themeDir.'/resources/views/blocks/hero';
        $metadata = json_decode((string) file_get_contents($blockDir.'/block.json'), true);

        expect($blockDir.'/render.blade.php')->toBeFile()
            ->and($blockDir.'/save.jsx')->not->toBeFile()
            ->and($this->themeDir.'/resources/blocks')->not->toBeDirectory()
            ->and($metadata['render'])->toBe('file:./render.blade.php')
            ->and((string) file_get_contents($blockDir.'/index.jsx'))->toContain('save: () => null');
    });

    it('generates a render.blade.php without leftover placeholders', function (): void {
        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'test-theme', '--title' => "Owner's Hero"]);

        $render = (string) file_get_contents($this->themeDir.'/resources/views/blocks/hero/render.blade.php');

        expect($render)->not->toMatch('/\{\{ (blockFullName|title|targetSlug) \}\}/')
            ->and($render)->toContain("{{ __('Owner\\'s Hero', 'test-theme') }}")
            ->and($render)->toContain('{!! get_block_wrapper_attributes() !!}')
            ->and($render)->toContain('test-theme/hero');
    });

    it('creates a static block with --static', function (): void {
        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'test-theme', '--static' => true])
            ->assertSuccessful();

        $blockDir = $this->themeDir.'/resources/views/blocks/hero';
        $metadata = json_decode((string) file_get_contents($blockDir.'/block.json'), true);

        expect($blockDir.'/save.jsx')->toBeFile()
            ->and($blockDir.'/render.blade.php')->not->toBeFile()
            ->and($metadata)->not->toHaveKey('render');
    });

    it('still accepts the deprecated --dynamic option', function (): void {
        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'test-theme', '--dynamic' => true])
            ->expectsOutputToContain('--dynamic is deprecated')
            ->assertSuccessful();

        expect($this->themeDir.'/resources/views/blocks/hero/render.blade.php')->toBeFile();
    });

    it('bootstraps the provider and Vite entries for every block asset on the first block', function (): void {
        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'test-theme'])->assertSuccessful();

        expectValidViteConfig($this->themeDir.'/vite.config.js');

        $vite = (string) file_get_contents($this->themeDir.'/vite.config.js');
        preg_match('/refresh:\s*\[(.*?)\]/s', $vite, $refresh);

        expect((string) file_get_contents($this->themeDir.'/app/Providers/BlocksServiceProvider.php'))->toContain("'/resources/views/blocks'")
            ->and($vite)->toContain("'./resources/views/blocks/*/{index,view}.{js,jsx,ts,tsx}'")
            ->and($vite)->toContain("'./resources/views/blocks/*/{editor,style}.css'")
            ->and($vite)->toContain('...(hasBlocks ? [wordpressPlugin()] : [])')
            ->and($vite)->not->toContain('./resources/blocks/')
            ->and($refresh[1])->not->toContain('resources/views/blocks')
            ->and($refresh[1])->toContain("'themes/'+themeName+'/resources/views/**/*.blade.php'")
            ->and($refresh[1])->toContain("...refreshPaths.filter((refreshPath) => refreshPath !== 'resources/views/**')");
    });

    it('patches the refresh paths only once', function (): void {
        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'test-theme'])->assertSuccessful();
        $afterFirstBlock = (string) file_get_contents($this->themeDir.'/vite.config.js');

        $this->artisan('pollora:make:block', ['name' => 'card', '--theme' => 'test-theme'])->assertSuccessful();

        expect((string) file_get_contents($this->themeDir.'/vite.config.js'))->toBe($afterFirstBlock);
    });

    it('does not bootstrap again a theme with blocks in resources/blocks, but builds the new location', function (): void {
        mkdir($this->themeDir.'/resources/blocks/legacy', 0755, true);
        file_put_contents($this->themeDir.'/vite.config.js', MAKE_BLOCK_LEGACY_VITE_CONFIG);
        $packageJson = (string) file_get_contents($this->themeDir.'/package.json');

        $this->artisan('pollora:make:block', ['name' => 'hero', '--theme' => 'test-theme'])->assertSuccessful();

        expectValidViteConfig($this->themeDir.'/vite.config.js');

        $vite = (string) file_get_contents($this->themeDir.'/vite.config.js');

        expect($this->themeDir.'/app/Providers/BlocksServiceProvider.php')->not->toBeFile()
            ->and((string) file_get_contents($this->themeDir.'/package.json'))->toBe($packageJson)
            ->and($vite)->toContain("'./resources/views/blocks/*/{index,view}.{js,jsx,ts,tsx}'")
            ->and($vite)->toContain("'./resources/blocks/*/{editor,style}.css'")
            ->and($vite)->not->toContain("'resources/blocks/**'")
            ->and($vite)->toContain("'resources/views/**/*.blade.php'");
    });
});
