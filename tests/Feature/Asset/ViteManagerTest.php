<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Pollora\Asset\Application\Services\MissingBuilds;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;
use Pollora\Asset\Infrastructure\Services\ViteManager;
use Pollora\Asset\UI\Http\MissingBuildNotice;

beforeEach(function (): void {
    // AssetServiceProvider's binding: one record of missing builds per request
    app()->singleton(MissingBuilds::class);

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

    it('renders the Vite client of its own dev server', function (): void {
        $theme = new ViteManager(new AssetContainer('theme', [
            'hot_file' => $this->hotFile,
            'build_directory' => $this->buildRoot.'/theme',
            'manifest_path' => 'manifest.json',
            'base_path' => 'resources/assets/',
        ]));

        // Created later: must not change what the theme renders
        new ViteManager(new AssetContainer('plugin.demo', [
            'hot_file' => sys_get_temp_dir().'/missing-'.uniqid().'.hot',
            'build_directory' => $this->buildRoot.'/plugin',
            'manifest_path' => 'manifest.json',
            'base_path' => 'resources/assets/',
        ]));

        expect($theme->getViteClientHtml())->toContain('https://example.test:5173/@vite/client');
    });
});

/**
 * A container with neither a dev server nor a manifest: the build was never
 * run, failed, or public/build was deleted. Laravel's Vite threw while
 * WordPress booted and every request answered 500 (Pollora/pollora#79).
 */
describe('ViteManager without a build', function (): void {
    beforeEach(function (): void {
        $this->unbuilt = new ViteManager(new AssetContainer('theme', [
            'hot_file' => sys_get_temp_dir().'/missing-'.uniqid().'.hot',
            'build_directory' => $this->buildRoot.'/theme',
            'manifest_path' => 'manifest.json',
            'base_path' => 'resources/assets/',
        ]));
    });

    it('gives no asset URLs instead of throwing', function (): void {
        expect($this->unbuilt->getAssetUrls(['app.js']))->toBe(['js' => [], 'css' => []])
            ->and($this->unbuilt->asset('images/logo.svg'))->toBe('')
            ->and($this->unbuilt->isMissingBuild())->toBeTrue();
    });

    it('records the missing build once, with its manifest', function (): void {
        Log::spy();

        $this->unbuilt->getAssetUrls(['app.js']);
        $this->unbuilt->getAssetUrls(['editor.js']);
        $this->unbuilt->asset('images/logo.svg');

        expect(resolve(MissingBuilds::class)->all())->toBe([
            'theme' => public_path($this->buildRoot.'/theme/manifest.json'),
        ]);

        Log::shouldHaveReceived('warning')->once();
    });

    it('records nothing for a built container or one served by the dev server', function (): void {
        $built = new ViteManager(new AssetContainer('plugin.demo', [
            'hot_file' => sys_get_temp_dir().'/missing-'.uniqid().'.hot',
            'build_directory' => $this->buildRoot.'/plugin',
            'manifest_path' => 'manifest.json',
        ]));
        $hot = new ViteManager(new AssetContainer('theme.hot', [
            'hot_file' => $this->hotFile,
            'build_directory' => $this->buildRoot.'/missing',
            'manifest_path' => 'manifest.json',
        ]));

        expect($built->isMissingBuild())->toBeFalse()
            ->and($hot->isMissingBuild())->toBeFalse()
            ->and(resolve(MissingBuilds::class)->all())->toBe([]);
    });
});

describe('MissingBuildNotice', function (): void {
    beforeEach(function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
    });

    it('names the missing build and the commands to run', function (): void {
        resolve(MissingBuilds::class)->record('theme', base_path('public/build/theme/default/manifest.json'));
        resolve(MissingBuilds::class)->record('theme.blocks', base_path('public/build/theme/default/manifest.json'));

        ob_start();
        resolve(MissingBuildNotice::class)->render();
        $notice = (string) ob_get_clean();

        expect($notice)
            ->toContain('Pollora: assets not built')
            ->toContain('theme, theme.blocks')
            ->and(substr_count($notice, 'public/build/theme/default/manifest.json'))->toBe(1)
            ->and($notice)
            ->toContain('npm install && npm run build')
            ->toContain('php artisan pollora:doctor');
    });

    it('stays silent when every build is there', function (): void {
        ob_start();
        resolve(MissingBuildNotice::class)->render();

        expect(ob_get_clean())->toBe('');
    });
});
