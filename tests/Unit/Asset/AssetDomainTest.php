<?php

declare(strict_types=1);

require_once __DIR__.'/../helpers.php';

use Pollora\Asset\Domain\Models\Asset;
use Pollora\Asset\Domain\Models\AssetFile;
use Pollora\Asset\Domain\Models\ViteManager;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;

describe('Asset domain model', function () {
    it('can be instantiated with name, path, and attributes', function () {
        $asset = new Asset('main', 'assets/main.js', ['type' => 'js']);
        expect($asset->getName())->toBe('main')
            ->and($asset->getPath())->toBe('assets/main.js')
            ->and($asset->getAttributes())->toBe(['type' => 'js']);
    });
});

describe('AssetFile domain model', function () {
    it('can be instantiated and return filename and container', function () {
        $file = new AssetFile('assets/app.css');
        expect($file->getFilename())->toBe('assets/app.css')
            ->and($file->getAssetContainer())->toBe('theme');
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
        app()->instance('path.public', '/tmp/public');
    });
    it('returns stub values for all interface methods', function () {
        $vite = new ViteManager;
        expect($vite->container())->toBeInstanceOf(AssetContainer::class)
            ->and($vite->getAssetUrls(['entry.js']))->toBeArray()->toBeEmpty()
            ->and($vite->asset('foo.js'))->toBe('foo.js')
            ->and($vite->isRunningHot())->toBeFalse()
            ->and($vite->getViteClientHtml())->toBe('');
    });
});
