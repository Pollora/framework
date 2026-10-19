<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Filters;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Application;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Media\Domain\Contracts\MediaStorage;
use Pollora\Media\Domain\Models\MediaConfig;
use Pollora\Media\Infrastructure\Laravel\MediaCheck;
use Pollora\Media\Infrastructure\Laravel\MediaServiceProvider;
use Pollora\Media\Infrastructure\WordPress\StreamWrapper;
use Pollora\Media\Infrastructure\WordPress\WordPressMedia;

/**
 * The media library on a Laravel disk, with the real provider, the real filesystem manager and a real local
 * disk; only WordPress's own functions are out of the picture. The package's classes are tested in the package.
 */
beforeEach(function (): void {
    Monkey\setUp();
    $this->root = sys_get_temp_dir().'/pollora-media-contract-'.bin2hex(random_bytes(5));
    mkdir($this->root, 0777, true);

    $this->makeApp = function (array $media): array {
        $app = new Application(sys_get_temp_dir());
        $app->instance('config', $config = new Repository([
            'media' => $media,
            'filesystems' => ['disks' => ['uploads' => ['driver' => 'local', 'root' => $this->root, 'url' => 'https://site.test/content/uploads']]],
        ]));
        $app->singleton('filesystem', fn (Application $app): FilesystemManager => new FilesystemManager($app));
        $app->alias('filesystem', Factory::class);

        $provider = new MediaServiceProvider($app);
        $provider->register();

        return [$app, $provider];
    };
});

afterEach(function (): void {
    StreamWrapper::unregister();
    Monkey\tearDown();
    $remove = static function (string $path) use (&$remove): void {
        foreach (glob($path.'/{,.}[!.]*', GLOB_BRACE) ?: [] as $entry) {
            is_dir($entry) ? $remove($entry) : unlink($entry);
        }

        @rmdir($path);
    };
    $remove($this->root);
});

it('does nothing without a disk: WordPress keeps its uploads folder', function (): void {
    [, $provider] = ($this->makeApp)(['disk' => null]);
    Filters\expectAdded('upload_dir')->never();

    $provider->boot();

    expect(in_array('pollora', stream_get_wrappers(), true))->toBeFalse();
});

it('puts the media on the disk named by media.disk, with the URL of the disk', function (): void {
    [$app, $provider] = ($this->makeApp)(['disk' => 'uploads', 'legacy_urls' => []]);
    Filters\expectAdded('upload_dir')->once();
    Filters\expectAdded('pre_wp_unique_filename_file_list')->once();
    Filters\expectAdded('wp_resource_hints')->once();

    Monkey\Functions\expect('add_action')->once();

    $provider->boot();

    $config = $app->make(MediaConfig::class);
    $dirs = $app->make(WordPressMedia::class)->uploadDir([
        'path' => '/x/2026/10', 'url' => 'u/2026/10', 'subdir' => '/2026/10', 'basedir' => '/x', 'baseurl' => 'u', 'error' => false,
    ]);

    expect($config->url)->toBe('https://site.test/content/uploads')
        ->and($dirs['baseurl'])->toBe('https://site.test/content/uploads')
        ->and($dirs['url'])->toBe('https://site.test/content/uploads/2026/10')
        ->and(file_put_contents($dirs['path'].'/photo.jpg', 'bytes'))->toBe(5)
        ->and(file_get_contents($this->root.'/2026/10/photo.jpg'))->toBe('bytes')
        ->and($app->make(MediaStorage::class)->exists('2026/10/photo.jpg'))->toBeTrue();
});

it('takes media.url over the URL of the disk, and reads the other settings', function (): void {
    [$app] = ($this->makeApp)([
        'disk' => 'uploads', 'url' => 'https://media.client.fr/', 'legacy_urls' => ['https://old.test/content/uploads/'],
        'cache_control' => 'public, max-age=60', 'private' => ['expiry' => '+1 hour'], 'async_thumbnails' => true,
    ]);

    $config = $app->make(MediaConfig::class);

    expect($config->url)->toBe('https://media.client.fr')
        ->and($config->legacyUrls)->toBe(['https://old.test/content/uploads'])
        ->and($config->cacheControl)->toBe('public, max-age=60')
        ->and($config->privateExpiry)->toBe('+1 hour')
        ->and($config->asyncThumbnails)->toBeTrue();
});

it('says what to set when neither media.url nor the disk has a URL', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads']);
    $app['config']->set('filesystems.disks.uploads.url', null);

    expect(fn () => $app->make(MediaConfig::class))->toThrow(RuntimeException::class, 'media.url');
});

it('ships a config file whose disk is off by default', function (): void {
    $defaults = require dirname(__DIR__, 3).'/src/Media/config/media.php';

    expect($defaults['disk'])->toBeNull()
        ->and($defaults['cache_control'])->toBe('public, max-age=31536000, immutable')
        ->and($defaults['private']['expiry'])->toBe('+6 hours')
        ->and($defaults['async_thumbnails'])->toBeFalse();
});

it('has a doctor check that is skipped without a disk, ok with one that works, and warns of files left in the local folder', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads']);
    $local = sys_get_temp_dir().'/pollora-media-local-'.bin2hex(random_bytes(4));
    mkdir($local.'/2026', 0777, true);
    file_put_contents($local.'/2026/stray.jpg', 'x');
    file_put_contents($this->root.'/there.jpg', 'x');
    file_put_contents($local.'/there.jpg', 'x');
    $make = static fn (?string $disk): MediaCheck => new MediaCheck(
        $disk,
        fn () => $app->make(MediaStorage::class),
        fn () => $app->make(MediaConfig::class),
        $local,
    );

    $off = $make(null)->run(RunContext::Console);
    $left = $make('uploads')->run(RunContext::Console);
    unlink($local.'/2026/stray.jpg');
    $clean = $make('uploads')->run(RunContext::Console);

    expect($off->status->name)->toBe('Skipped')
        ->and($left->status->name)->toBe('Warning')
        ->and($left->summary)->toContain('1 file(s)')
        ->and($left->details[0])->toBe('2026/stray.jpg is only local')
        ->and($clean->status->name)->toBe('Ok');
});
