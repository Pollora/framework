<?php

declare(strict_types=1);

use Pollora\Asset\Domain\Models\Asset;
use Pollora\Asset\Domain\Models\AssetFile;
use Pollora\Asset\Domain\Models\ViteManager;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;

describe('Asset domain model', function () {
    it('can be instantiated with name, path, and attributes', function () {
        $asset = new Asset('main', 'assets/main.js', ['type' => 'js']);
        expect($asset->getName())->toBe('main');
        expect($asset->getPath())->toBe('assets/main.js');
        expect($asset->getAttributes())->toBe(['type' => 'js']);
    });
});

describe('AssetFile domain model', function () {
    it('can be instantiated and return filename and container', function () {
        $file = new AssetFile('assets/app.css');
        expect($file->getFilename())->toBe('assets/app.css');
        expect($file->getAssetContainer())->toBe('theme');
    });
    it('can set a custom asset container', function () {
        $file = (new AssetFile('assets/app.css'))->from('custom');
        expect($file->getAssetContainer())->toBe('custom');
    });
    it('can be cast to string as filename', function () {
        $file = new AssetFile('assets/app.css');
        expect((string) $file)->toBe('assets/app.css');
    });
});

describe('ViteManager domain stub', function () {
    beforeEach(function () {
        // Bind 'path.public' to avoid BindingResolutionException
        if (! function_exists('app')) {
            require_once __DIR__.'/helpers.php';
        }
        app()->instance('path.public', '/tmp/public');
    });
    it('returns stub values for all interface methods', function () {
        $vite = new ViteManager;
        expect($vite->container())->toBeInstanceOf(AssetContainer::class);
        expect($vite->getAssetUrls(['entry.js']))->toBeArray()->toBeEmpty();
        expect($vite->asset('foo.js'))->toBe('foo.js');
        expect($vite->isRunningHot())->toBeFalse();
        expect($vite->getViteClientHtml())->toBe('');
    });
});
