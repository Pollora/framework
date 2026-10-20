<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Filters;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Hook\Async\PendingAsync;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\HookInterface;
use Pollora\Media\Application\Services\ImageSizes;
use Pollora\Media\Domain\Contracts\MediaStorage;
use Pollora\Media\Domain\Enums\Fit;
use Pollora\Media\Domain\Events\MediaDeleted;
use Pollora\Media\Domain\Events\MediaStored;
use Pollora\Media\Domain\Events\MediaVisibilityChanged;
use Pollora\Media\Domain\Models\ImageSizeSpec;
use Pollora\Media\Domain\Models\MediaConfig;
use Pollora\Media\Domain\Services\ImgproxySigner;
use Pollora\Media\Infrastructure\BunnyThumbnailDriver;
use Pollora\Media\Infrastructure\ImgproxyThumbnailDriver;
use Pollora\Media\Infrastructure\Laravel\Console\MediaMigrateCommand;
use Pollora\Media\Infrastructure\Laravel\Console\MediaThumbsExportCommand;
use Pollora\Media\Infrastructure\Laravel\Console\MediaThumbsWarmCommand;
use Pollora\Media\Infrastructure\Laravel\MediaCheck;
use Pollora\Media\Infrastructure\Laravel\MediaServiceProvider;
use Pollora\Media\Infrastructure\PolloraThumbnailDriver;
use Pollora\Media\Infrastructure\WordPress\AsyncThumbnails;
use Pollora\Media\Infrastructure\WordPress\StreamWrapper;
use Pollora\Media\Infrastructure\WordPress\WordPressMedia;
use Pollora\Support\Facades\ImageSize;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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
        $app->instance(Action::class, $this->action = Mockery::mock(Action::class)->shouldIgnoreMissing());

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

    Monkey\Actions\expectAdded('delete_attachment')->once();

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

it('hands the framework hook service to the module, so the thumbnails of an upload can be deferred', function (): void {
    [$app, $provider] = ($this->makeApp)(['disk' => 'uploads', 'async_thumbnails' => true]);
    Filters\expectAdded('intermediate_image_sizes_advanced')->twice(); // the thumbnails, and the declared sizes
    Filters\expectAdded('wp_generate_attachment_metadata')->times(3); // the thumbnails, private media, the declared sizes
    $this->action->shouldReceive('add')->with(AsyncThumbnails::HOOK, Mockery::type('array'))->once()->andReturn(Mockery::mock(HookInterface::class));
    $this->action->shouldReceive('async')->once()->andReturn(new PendingAsync(static function (): void {}));

    $provider->boot();
});

it('relays the three actions of the module as Laravel events', function (): void {
    [$app, $provider] = ($this->makeApp)(['disk' => 'uploads']);
    $dispatched = [];
    $app->instance('events', $events = Mockery::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->andReturnUsing(function (object $event) use (&$dispatched): void {
        $dispatched[] = $event;
    });
    $callbacks = [];
    $this->action->shouldReceive('add')->andReturnUsing(function (string $hook, callable $callback) use (&$callbacks) {
        $callbacks[$hook] = $callback;

        return Mockery::mock(HookInterface::class);
    });
    Filters\expectAdded('upload_dir');

    $provider->boot();
    $callbacks['pollora/media/stored']('2026/a.jpg', 5);
    $callbacks['pollora/media/deleted'](7, ['2026/a.jpg']);
    $callbacks['pollora/media/visibility_changed'](7, true);

    expect($dispatched)->toHaveCount(3)
        ->and($dispatched[0])->toEqual(new MediaStored('2026/a.jpg', 5))
        ->and($dispatched[1])->toEqual(new MediaDeleted(7, ['2026/a.jpg']))
        ->and($dispatched[2])->toEqual(new MediaVisibilityChanged(7, true));
});

it('signs a URL through the Laravel disk, and gives null when the disk cannot', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads']);

    expect($app->make(MediaStorage::class)->temporaryUrl('a.jpg', new DateTimeImmutable('+1 hour')))->toBeNull();
});

it('has a doctor check that warns of private media on a disk that cannot sign', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads']);
    Monkey\Functions\when('get_posts')->justReturn([7, 8]);
    $check = new MediaCheck(
        'uploads',
        fn () => $app->make(MediaStorage::class),
        fn () => $app->make(MediaConfig::class),
        sys_get_temp_dir().'/pollora-media-no-local-'.bin2hex(random_bytes(3)),
    );

    $result = $check->run(RunContext::Console);

    expect($result->status->name)->toBe('Warning')
        ->and($result->summary)->toContain('cannot sign URLs')->toContain('2 media');
});

it('declares image sizes through the facade, on the registry the module uses, signed with the application key', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads']);
    $app['config']->set('app.key', 'base64:test-application-key');
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);

    try {
        ImageSize::add('card')->cover(600, 400)->webp(75);
        ImageSize::add('hero')->scale(width: 1920)->widths([640]);

        $sizes = $app->make(ImageSizes::class);
        $file = $sizes->names()->for('2026/10/photo.jpg', $sizes->get('card'), 3000, 2000);

        expect(array_keys($sizes->all()))->toBe(['card', 'hero'])
            ->and($sizes)->toBe($app->make(ImageSizes::class))
            ->and($app->make(WordPressMedia::class)->imageSizes())->toBe($sizes)
            ->and($file)->toMatch('#^2026/10/photo-card-600x400-[0-9a-f]{12}\.webp$#');

        $app['config']->set('app.key', 'base64:another-key');
        expect($sizes->names()->for('2026/10/photo.jpg', $sizes->get('card'), 3000, 2000))->not->toBe($file);
    } finally {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
    }
});

it('has a doctor check that reports declarations it could not make into a size', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads']);
    $sizes = new ImageSizes(fn (): string => 'k');
    $sizes->add('broken')->cover(600);
    $check = new MediaCheck(
        'uploads',
        fn () => $app->make(MediaStorage::class),
        fn () => $app->make(MediaConfig::class),
        sys_get_temp_dir().'/pollora-media-no-local-'.bin2hex(random_bytes(3)),
        fn (): ImageSizes => $sizes,
    );
    Monkey\Functions\when('get_posts')->justReturn([]);

    $result = $check->run(RunContext::Console);

    expect($result->status->name)->toBe('Warning')
        ->and($result->summary)->toContain('declared image sizes')
        ->and($result->details[0])->toContain('broken');
});

it('hands the limits of the sizes made on demand to the module, from media.thumbnails', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads', 'thumbnails' => ['concurrency' => 2, 'wait' => 3, 'max_pixels' => 1000]]);

    $limits = (new ReflectionProperty(WordPressMedia::class, 'limits'))->getValue($app->make(WordPressMedia::class));

    expect([$limits->concurrency, $limits->wait, $limits->maxPixels, $limits->maxBytes])->toBe([2, 3, 1000, 31_457_280]);
});

it('has a doctor check that reports a .htaccess that cuts the fallback in the uploads folder', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads']);
    $local = sys_get_temp_dir().'/pollora-media-htaccess-'.bin2hex(random_bytes(3));
    mkdir($local, 0777, true);
    file_put_contents($local.'/.htaccess', "RewriteEngine On\nRewriteRule .* - [F]\n");
    Monkey\Functions\when('get_posts')->justReturn([]);
    $check = new MediaCheck('uploads', fn () => $app->make(MediaStorage::class), fn () => $app->make(MediaConfig::class), $local);

    $result = $check->run(RunContext::Console);
    unlink($local.'/.htaccess');
    rmdir($local);

    expect($result->status->name)->toBe('Warning')->and($result->summary)->toContain('.htaccess');
});

it('makes the sizes itself unless media.thumbnails.driver says otherwise', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads']);

    expect($app->make(WordPressMedia::class)->thumbnailDriver())->toBeInstanceOf(PolloraThumbnailDriver::class);
});

it('hands the Bunny driver to the module, which makes no file', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads', 'thumbnails' => ['driver' => 'bunny']]);
    $driver = $app->make(WordPressMedia::class)->thumbnailDriver();

    expect($driver)->toBeInstanceOf(BunnyThumbnailDriver::class)->and($driver->makesFiles())->toBeFalse();
});

it('hands the imgproxy driver to the module, with the URL, key, salt and signature size of media.thumbnails.imgproxy', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads', 'url' => 'https://media.client.fr', 'thumbnails' => ['driver' => 'imgproxy', 'imgproxy' => [
        'url' => 'https://img.client.fr/', 'key' => '736563726574', 'salt' => '68656C6C6F', 'signature_size' => '16',
    ]]]);
    $driver = $app->make(WordPressMedia::class)->thumbnailDriver();
    $url = $driver->url('2026/10/photo.jpg', new ImageSizeSpec('card', Fit::Cover, 600, 400), 3000, 2000);
    $path = '/card/plain/https://media.client.fr/2026/10/photo.jpg@webp';

    expect($driver)->toBeInstanceOf(ImgproxyThumbnailDriver::class)
        ->and($url)->toBe('https://img.client.fr/'.(new ImgproxySigner('736563726574', '68656C6C6F', 16))->sign($path).$path);
});

it('takes the bucket of the disk as the imgproxy source when the source is s3', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads', 'thumbnails' => ['driver' => 'imgproxy', 'imgproxy' => [
        'url' => 'https://img.client.fr', 'key' => '736563726574', 'salt' => '68656C6C6F', 'source' => 's3',
    ]]]);
    $app->make('config')->set('filesystems.disks.uploads.bucket', 'client-media');

    $url = $app->make(WordPressMedia::class)->thumbnailDriver()->url('a.jpg', new ImageSizeSpec('card', Fit::Cover, 600, 400), 3000, 2000);

    expect($url)->toContain('/card/plain/s3://client-media/a.jpg@webp');
});

it('ships the settings of the drivers of the sizes, off by default', function (): void {
    $config = require __DIR__.'/../../../src/Media/config/media.php';

    expect($config['thumbnails']['driver'])->toBe('pollora')
        ->and($config['thumbnails']['bunny'])->toHaveKeys(['pull_zone', 'api_key'])
        ->and($config['thumbnails']['imgproxy'])->toHaveKeys(['url', 'key', 'salt', 'signature_size', 'source'])
        ->and($config['thumbnails']['imgproxy']['signature_size'])->toBe(32);
});

/**
 * @param  array<string, mixed>  $input
 * @return array{0: int, 1: string}
 */
function runMediaCommand(Application $app, string $command, array $input = []): array
{
    $instance = $app->make($command);
    $instance->setLaravel($app);

    $output = new BufferedOutput;
    $status = $instance->run(new ArrayInput($input), $output);

    return [$status, $output->fetch()];
}

it('exports the declared sizes as imgproxy presets with pollora:media:thumbs:export, without the key or the salt', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads', 'url' => 'https://media.client.fr', 'thumbnails' => ['driver' => 'imgproxy', 'imgproxy' => [
        'url' => 'https://img.client.fr', 'key' => '736563726574', 'salt' => '68656C6C6F',
    ]]]);
    $app->make(ImageSizes::class)->add('card')->cover(600, 400)->webp(75);

    [$status, $output] = runMediaCommand($app, MediaThumbsExportCommand::class);

    expect($status)->toBe(0)
        ->and($output)->toContain('IMGPROXY_PRESETS=card=resize:fill-down:600:400:0/quality:75')
        ->and($output)->toContain('IMGPROXY_ONLY_PRESETS=true')
        ->and($output)->toContain('IMGPROXY_ALLOWED_SOURCES=https://media.client.fr/')
        ->and($output)->not->toContain('736563726574')->not->toContain('68656C6C6F');
});

it('says what to set when the pull zone or the key is missing for a Bunny export', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads', 'thumbnails' => ['driver' => 'bunny']]);

    [$status, $output] = runMediaCommand($app, MediaThumbsExportCommand::class);

    expect($status)->toBe(1)->and($output)->toContain('pull_zone');
});

it('has nothing to export for the default driver, and nothing to warm for a service', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads']);
    [$status, $output] = runMediaCommand($app, MediaThumbsExportCommand::class);

    expect($status)->toBe(1)->and($output)->toContain('nothing to export');

    [$bunny] = ($this->makeApp)(['disk' => 'uploads', 'thumbnails' => ['driver' => 'bunny']]);
    [$status, $output] = runMediaCommand($bunny, MediaThumbsWarmCommand::class);

    expect($status)->toBe(0)->and($output)->toContain('nothing to warm');
});

it('has a doctor check that reports a driver of the sizes it could not use', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads', 'thumbnails' => ['driver' => 'imgproxy']]);
    Monkey\Functions\when('get_posts')->justReturn([]);
    $log = tempnam(sys_get_temp_dir(), 'media-log');
    $previous = ini_set('error_log', $log);

    try {
        $result = $app->make(MediaCheck::class)->run(RunContext::Console);
    } finally {
        ini_set('error_log', (string) $previous);
        unlink($log);
    }

    expect($result->status->name)->toBe('Warning')->and($result->details[0])->toContain('"imgproxy"');
});

function removeMediaTestDirectory(string $path): void
{
    foreach (glob($path.'/{,.}[!.]*', GLOB_BRACE) ?: [] as $entry) {
        is_dir($entry) ? removeMediaTestDirectory($entry) : unlink($entry);
    }

    @rmdir($path);
}

it('leaves out of pollora:media:migrate what --exclude names, and says how many', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads']);
    $source = $this->root.'-source';
    mkdir($source.'/2026/10', 0777, true);
    mkdir($source.'/woocommerce_uploads', 0777, true);
    mkdir($source.'/wc-logs', 0777, true);
    file_put_contents($source.'/2026/10/photo.jpg', 'photo');
    file_put_contents($source.'/woocommerce_uploads/invoice.pdf', 'secret');
    file_put_contents($source.'/wc-logs/error.log', 'log');
    $app['config']->set('filesystems.disks.source', ['driver' => 'local', 'root' => $source]);

    [$status, $output] = runMediaCommand($app, MediaMigrateCommand::class, [
        '--from' => 'source', '--execute' => true, '--exclude' => ['woocommerce_uploads/*', 'wc-logs/*'],
    ]);

    $left = [file_exists($this->root.'/woocommerce_uploads/invoice.pdf'), file_exists($this->root.'/wc-logs/error.log')];
    $copied = file_exists($this->root.'/2026/10/photo.jpg');
    removeMediaTestDirectory($source);

    expect($status)->toBe(0)
        ->and($copied)->toBeTrue()
        ->and($left)->toBe([false, false])
        ->and($output)->toContain('Copied 1 file(s)')
        ->and($output)->toContain('2 left out');
});

it('copies everything in pollora:media:migrate when nothing is excluded', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads']);
    $source = $this->root.'-source-all';
    mkdir($source.'/wc-logs', 0777, true);
    file_put_contents($source.'/wc-logs/error.log', 'log');
    $app['config']->set('filesystems.disks.source', ['driver' => 'local', 'root' => $source]);

    [$status, $output] = runMediaCommand($app, MediaMigrateCommand::class, ['--from' => 'source', '--execute' => true]);
    $copied = file_exists($this->root.'/wc-logs/error.log');
    removeMediaTestDirectory($source);

    expect($status)->toBe(0)->and($copied)->toBeTrue()->and($output)->toContain('0 left out');
});

it('does not call a deliberately local folder a file left behind', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads']);
    Monkey\Functions\when('get_posts')->justReturn([]);
    $local = sys_get_temp_dir().'/pollora-media-local-'.bin2hex(random_bytes(4));
    mkdir($local.'/woocommerce_uploads', 0777, true);
    mkdir($local.'/wc-logs', 0777, true);
    file_put_contents($local.'/woocommerce_uploads/invoice.pdf', 'x');
    file_put_contents($local.'/wc-logs/error.log', 'x');

    $result = (new MediaCheck('uploads', fn () => $app->make(MediaStorage::class), fn () => $app->make(MediaConfig::class), $local))->run(RunContext::Console);

    expect($result->status->name)->toBe('Ok');
});

it('has a doctor check that warns when a folder of private files is on the media disk, where it is served to anyone', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads']);
    $local = sys_get_temp_dir().'/pollora-media-local-'.bin2hex(random_bytes(4));
    mkdir($local, 0777, true);
    mkdir($this->root.'/woocommerce_uploads', 0777, true);
    file_put_contents($this->root.'/woocommerce_uploads/invoice.pdf', 'x');

    $result = (new MediaCheck('uploads', fn () => $app->make(MediaStorage::class), fn () => $app->make(MediaConfig::class), $local))->run(RunContext::Console);

    expect($result->status->name)->toBe('Warning')
        ->and($result->summary)->toContain('woocommerce_uploads')
        ->and($result->fix)->toContain('--exclude');
});

/**
 * A local adapter that remembers the options each write was given, as an S3 adapter would read them.
 */
final class RecordingMediaAdapter implements FilesystemAdapter
{
    /** @var array<string, array{cache: mixed, visibility: mixed}> */
    public array $writes = [];

    private readonly LocalFilesystemAdapter $inner;

    public function __construct(string $root)
    {
        $this->inner = new LocalFilesystemAdapter($root);
    }

    public function fileExists(string $path): bool
    {
        return $this->inner->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->inner->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->writes[$path] = ['cache' => $config->get('CacheControl'), 'visibility' => $config->get('visibility')];
        $this->inner->write($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->writes[$path] = ['cache' => $config->get('CacheControl'), 'visibility' => $config->get('visibility')];
        $this->inner->writeStream($path, $contents, $config);
    }

    public function read(string $path): string
    {
        return $this->inner->read($path);
    }

    public function readStream(string $path)
    {
        return $this->inner->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->inner->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->inner->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->inner->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->inner->setVisibility($path, $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->inner->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->inner->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->inner->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->inner->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return $this->inner->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->inner->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->inner->copy($source, $destination, $config);
    }
}

it('sends the cache header of media.cache_control, and the visibility of the source, with each file pollora:media:migrate copies', function (): void {
    [$app] = ($this->makeApp)(['disk' => 'uploads', 'cache_control' => 'public, max-age=31536000, immutable']);
    $source = $this->root.'-source-headers';
    mkdir($source, 0777, true);
    file_put_contents($source.'/photo.jpg', 'photo');
    $app['config']->set('filesystems.disks.source', ['driver' => 'local', 'root' => $source]);
    $adapter = new RecordingMediaAdapter($this->root);
    $app['filesystem']->set('uploads', new Illuminate\Filesystem\FilesystemAdapter(new Filesystem($adapter), $adapter, ['root' => $this->root]));

    [$status] = runMediaCommand($app, MediaMigrateCommand::class, ['--from' => 'source', '--execute' => true]);
    removeMediaTestDirectory($source);

    expect($status)->toBe(0)
        ->and($adapter->writes['photo.jpg']['cache'])->toBe('public, max-age=31536000, immutable')
        ->and($adapter->writes['photo.jpg']['visibility'])->toBe('public');
});
