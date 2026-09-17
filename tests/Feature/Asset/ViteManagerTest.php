<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;
use Pollora\Asset\Infrastructure\Services\ViteManager;

beforeEach(function (): void {
    $this->buildRoot = 'build/vite-manager-test-'.uniqid();
    $this->hotFile = sys_get_temp_dir().'/vite-manager-test-'.uniqid().'.hot';

    // A plugin built for production
    mkdir(public_path($this->buildRoot.'/plugin'), 0755, true);
    file_put_contents(public_path($this->buildRoot.'/plugin/manifest.json'), json_encode([
        'resources/assets/admin.js' => ['file' => 'assets/admin-123.js', 'css' => ['assets/admin-456.css']],
    ]));

    // A theme served by the Vite dev server
    file_put_contents($this->hotFile, 'https://example.test:5173');
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory(public_path($this->buildRoot));
    @unlink($this->hotFile);
});

describe('ViteManager with several containers', function (): void {
    it('keeps each container on its own hot file and build directory', function (): void {
        $plugin = new ViteManager(new AssetContainer('plugin.demo', [
            'hot_file' => sys_get_temp_dir().'/missing-'.uniqid().'.hot',
            'build_directory' => $this->buildRoot.'/plugin',
            'manifest_path' => 'manifest.json',
            'base_path' => 'resources/assets/',
        ]));

        // Created last, as the theme is when a block or theme asset is registered after the plugin
        $theme = new ViteManager(new AssetContainer('theme', [
            'hot_file' => $this->hotFile,
            'build_directory' => $this->buildRoot.'/theme',
            'manifest_path' => 'manifest.json',
            'base_path' => 'resources/assets/',
        ]));

        expect($theme->isRunningHot())->toBeTrue()
            ->and($plugin->isRunningHot())->toBeFalse()
            ->and($plugin->getAssetUrls(['admin.js']))->toBe([
                'js' => [asset($this->buildRoot.'/plugin/assets/admin-123.js')],
                'css' => [asset($this->buildRoot.'/plugin/assets/admin-456.css')],
            ]);
    });
});
