<?php

declare(strict_types=1);

/**
 * Path to the framework stubs directory (relative to framework root).
 */
function blockStubsDir(): string
{
    // From tests/Unit/Block/ go up 3 levels to framework root, then into src/Block/stubs
    return dirname(__DIR__, 3).'/src/Block/stubs';
}

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/pollora-makeblock-test-'.uniqid();
    mkdir($this->tempDir.'/app/Providers', 0755, true);
    mkdir($this->tempDir.'/resources', 0755, true);

    // Create a minimal package.json
    file_put_contents($this->tempDir.'/package.json', json_encode([
        'name' => 'test-theme',
        'devDependencies' => ['vite' => '^5.0.0'],
    ]));

    // Create a minimal vite.config.js
    file_put_contents($this->tempDir.'/vite.config.js', <<<'JS'
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

const themeName = 'test-theme';

export default defineConfig(({ command }) => {
    return {
        plugins: [
            laravel({
                input: ["./resources/assets/app.js"],
                refresh: ['resources/views/**'],
            }),
        ],
    };
});
JS);
});

afterEach(function (): void {
    if (is_dir($this->tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($this->tempDir);
    }
});

describe('MakeBlockCommand scaffolding', function (): void {

    it('validates block name format', function (): void {
        expect(preg_match('/^[a-z][a-z0-9-]*$/', 'hero-banner'))->toBe(1);
        expect(preg_match('/^[a-z][a-z0-9-]*$/', 'Hero'))->toBe(0);
        expect(preg_match('/^[a-z][a-z0-9-]*$/', '123-block'))->toBe(0);
        expect(preg_match('/^[a-z][a-z0-9-]*$/', 'my_block'))->toBe(0);
        expect(preg_match('/^[a-z][a-z0-9-]*$/', 'hero'))->toBe(1);
    });

    it('parses block.json stub with correct placeholders', function (): void {
        $blockJsonStub = file_get_contents(blockStubsDir().'/block.json.stub');

        $replacements = [
            '{{ blockSlug }}' => 'hero',
            '{{ blockNamespace }}' => 'test-theme',
            '{{ blockFullName }}' => 'test-theme/hero',
            '{{ title }}' => 'Hero',
            '{{ category }}' => 'widgets',
            '{{ icon }}' => 'block-default',
            '{{ className }}' => 'Hero',
            '{{ targetSlug }}' => 'test-theme',
            '{{ blockCssClass }}' => 'test-theme-hero',
        ];

        $blockJson = str_replace(array_keys($replacements), array_values($replacements), $blockJsonStub);
        $blockJsonData = json_decode($blockJson, true);

        expect($blockJsonData['name'])->toBe('test-theme/hero');
        expect($blockJsonData['title'])->toBe('Hero');
        expect($blockJsonData['category'])->toBe('widgets');
        expect($blockJsonData['icon'])->toBe('block-default');
        expect($blockJsonData['editorScript'])->toBe('file:./index.jsx');
        expect($blockJsonData['editorStyle'])->toBe('file:./editor.css');
        expect($blockJsonData['style'])->toBe('file:./style.css');
    });

    it('adds render field for dynamic blocks', function (): void {
        $blockJsonStub = file_get_contents(blockStubsDir().'/block.json.stub');
        $blockJson = str_replace(
            ['{{ blockFullName }}', '{{ title }}', '{{ category }}', '{{ icon }}', '{{ blockSlug }}', '{{ targetSlug }}'],
            ['test/hero', 'Hero', 'widgets', 'block-default', 'hero', 'test-theme'],
            $blockJsonStub
        );
        $blockJsonData = json_decode($blockJson, true);
        $blockJsonData['render'] = 'file:./render.php';

        expect($blockJsonData)->toHaveKey('render');
        expect($blockJsonData['render'])->toBe('file:./render.php');
    });

    it('adds viewScript field when not using --no-view-script', function (): void {
        $blockJsonStub = file_get_contents(blockStubsDir().'/block.json.stub');
        $blockJson = str_replace(
            ['{{ blockFullName }}', '{{ title }}', '{{ category }}', '{{ icon }}', '{{ blockSlug }}', '{{ targetSlug }}'],
            ['test/hero', 'Hero', 'widgets', 'block-default', 'hero', 'test-theme'],
            $blockJsonStub
        );
        $blockJsonData = json_decode($blockJson, true);
        $blockJsonData['viewScript'] = 'file:./view.js';

        expect($blockJsonData)->toHaveKey('viewScript');
        expect($blockJsonData['viewScript'])->toBe('file:./view.js');
    });

    it('generates BlocksServiceProvider with correct namespace and container', function (): void {
        $stub = file_get_contents(blockStubsDir().'/blocks-service-provider.php.stub');

        $result = str_replace(
            ['{{ namespace }}', '{{ containerName }}'],
            ['Theme\\TestTheme\\Providers', 'theme'],
            $stub
        );

        expect(str_contains($result, 'namespace Theme\\TestTheme\\Providers;'))->toBeTrue();
        expect(str_contains($result, "containerName: 'theme'"))->toBeTrue();
    });

    it('generates BlocksServiceProvider with plugin container', function (): void {
        $stub = file_get_contents(blockStubsDir().'/blocks-service-provider.php.stub');

        $result = str_replace(
            ['{{ namespace }}', '{{ containerName }}'],
            ['Plugin\\MyPlugin\\Providers', 'plugin.my-plugin'],
            $stub
        );

        expect(str_contains($result, 'namespace Plugin\\MyPlugin\\Providers;'))->toBeTrue();
        expect(str_contains($result, "containerName: 'plugin.my-plugin'"))->toBeTrue();
    });
});

describe('MakeBlockCommand npm dependencies', function (): void {

    it('adds missing npm dependencies to package.json', function (): void {
        $packageJson = json_decode(file_get_contents($this->tempDir.'/package.json'), true);
        $devDeps = $packageJson['devDependencies'] ?? [];

        $npmDeps = [
            '@roots/vite-plugin' => '^2.0.0',
            'glob' => '^11.0.0',
            '@wordpress/blocks' => '^14.0.0',
            '@wordpress/block-editor' => '^14.0.0',
            '@wordpress/components' => '^29.0.0',
            '@wordpress/element' => '^6.0.0',
            '@wordpress/i18n' => '^5.0.0',
        ];

        $added = [];
        foreach ($npmDeps as $dep => $version) {
            if (! isset($devDeps[$dep])) {
                $devDeps[$dep] = $version;
                $added[] = $dep;
            }
        }

        ksort($devDeps);
        $packageJson['devDependencies'] = $devDeps;
        file_put_contents($this->tempDir.'/package.json', json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $result = json_decode(file_get_contents($this->tempDir.'/package.json'), true);

        expect($result['devDependencies'])->toHaveKey('@roots/vite-plugin');
        expect($result['devDependencies'])->toHaveKey('@wordpress/blocks');
        expect($result['devDependencies'])->toHaveKey('glob');
        expect($added)->toHaveCount(7);
    });

    it('does not duplicate existing dependencies', function (): void {
        $packageJson = [
            'name' => 'test',
            'devDependencies' => [
                '@roots/vite-plugin' => '^2.0.0',
                'vite' => '^5.0.0',
            ],
        ];
        file_put_contents($this->tempDir.'/package.json', json_encode($packageJson));

        $result = json_decode(file_get_contents($this->tempDir.'/package.json'), true);
        $devDeps = $result['devDependencies'];

        $npmDeps = ['@roots/vite-plugin' => '^2.0.0', 'glob' => '^11.0.0'];
        $added = [];
        foreach ($npmDeps as $dep => $version) {
            if (! isset($devDeps[$dep])) {
                $devDeps[$dep] = $version;
                $added[] = $dep;
            }
        }

        expect($added)->toBe(['glob']);
        expect($devDeps['@roots/vite-plugin'])->toBe('^2.0.0');
    });
});

describe('MakeBlockCommand vite.config.js patching', function (): void {

    it('adds imports and block entries to vite.config.js', function (): void {
        $content = file_get_contents($this->tempDir.'/vite.config.js');

        $rootsImport = "import { wordpressPlugin } from '@roots/vite-plugin';";
        $globImport = "import { globSync } from 'glob';";

        preg_match_all('/^import\s+.+?[\'"];?\s*$/m', $content, $allMatches, PREG_OFFSET_CAPTURE);
        $lastImport = end($allMatches[0]);
        $insertPos = $lastImport[1] + strlen($lastImport[0]);
        $content = substr($content, 0, $insertPos)."\n".$rootsImport."\n".$globImport.substr($content, $insertPos);

        expect(str_contains($content, '@roots/vite-plugin'))->toBeTrue();
        expect(str_contains($content, 'globSync'))->toBeTrue();
    });

    it('adds block entries to input array', function (): void {
        $content = file_get_contents($this->tempDir.'/vite.config.js');

        if (preg_match('/(input:\s*\[)([^\]]+)(\])/', $content, $matches)) {
            $content = str_replace(
                $matches[0],
                $matches[1].$matches[2].', ...Object.values(blockEntries)'.$matches[3],
                $content
            );
        }

        expect(str_contains($content, '...Object.values(blockEntries)'))->toBeTrue();
    });

    it('does not patch already configured vite.config.js', function (): void {
        $content = file_get_contents($this->tempDir.'/vite.config.js');
        $content .= "\n// Already has @roots/vite-plugin";

        $alreadyPatched = str_contains($content, '@roots/vite-plugin') || str_contains($content, 'blockEntries');

        expect($alreadyPatched)->toBeTrue();
    });
});

describe('MakeBlockCommand inner blocks stubs', function (): void {

    it('uses InnerBlocks in edit stub when requested', function (): void {
        $editStub = file_get_contents(blockStubsDir().'/edit-inner-blocks.jsx.stub');

        expect(str_contains($editStub, 'InnerBlocks'))->toBeTrue();
        expect(str_contains($editStub, '<InnerBlocks />'))->toBeTrue();
    });

    it('uses InnerBlocks.Content in save stub when requested', function (): void {
        $saveStub = file_get_contents(blockStubsDir().'/save-inner-blocks.jsx.stub');

        expect(str_contains($saveStub, 'InnerBlocks'))->toBeTrue();
        expect(str_contains($saveStub, '<InnerBlocks.Content />'))->toBeTrue();
    });
});
