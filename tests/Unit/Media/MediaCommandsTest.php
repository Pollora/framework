<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Pollora\Media\Application\Services\ImageSizes;
use Pollora\Media\Domain\Contracts\MediaStorage;
use Pollora\Media\Domain\Models\MediaConfig;
use Pollora\Media\Infrastructure\Flysystem\FlysystemMediaStorage;
use Pollora\Media\Infrastructure\Laravel\Console\MediaFilesCommand;
use Pollora\Media\Infrastructure\Laravel\Console\MediaStatusCommand;
use Pollora\Media\Infrastructure\Laravel\Console\MediaThumbsPruneCommand;
use Pollora\Media\Infrastructure\Laravel\Console\MediaThumbsWarmCommand;
use Pollora\Media\Infrastructure\WordPress\WordPressMedia;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @param  array<string, mixed>  $input
 * @return array{0: int, 1: string}
 */
function runMediaCommand(Command $command, Application $app, array $input = []): array
{
    Container::setInstance($app);
    $command->setLaravel($app);
    $output = new BufferedOutput;

    try {
        $code = $command->run(new ArrayInput($input), $output);
    } finally {
        Container::setInstance(new Container);
    }

    return [$code, $output->fetch()];
}

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pollora-media-commands-'.bin2hex(random_bytes(4));
    mkdir($this->root, 0777, true);

    $this->storage = new FlysystemMediaStorage(new Filesystem(new LocalFilesystemAdapter($this->root)));
    $this->config = new MediaConfig('https://cdn.example.test/uploads');
    $this->sizes = new ImageSizes(fn (): string => 'a-test-key');

    $this->app = new Application(sys_get_temp_dir());
    $this->app->instance('config', new Repository(['media' => ['disk' => 'cdn']]));
    $this->app->instance(MediaStorage::class, $this->storage);
    $this->app->instance(MediaConfig::class, $this->config);
    $this->app->instance(ImageSizes::class, $this->sizes);
});

afterEach(function (): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->root);
    Mockery::close();
});

describe('pollora:media:status', function (): void {
    it('says the module is off when no disk is configured', function (): void {
        $this->app->instance('config', new Repository(['media' => ['disk' => '']]));

        [$code, $output] = runMediaCommand(new MediaStatusCommand, $this->app);

        expect($code)->toBe(0)->and($output)->toContain('The media module is off');
    });

    it('reports the disk and its public URL, and that the disk takes a file', function (): void {
        [$code, $output] = runMediaCommand(new MediaStatusCommand, $this->app);

        expect($code)->toBe(0)
            ->and($output)->toContain('cdn')
            ->and($output)->toContain('https://cdn.example.test/uploads')
            ->and($output)->toContain('The disk takes a file');
    });

    it('fails with the reason when the disk refuses a file', function (): void {
        $broken = Mockery::mock(MediaStorage::class);
        $broken->shouldReceive('write')->andThrow(new RuntimeException('bucket is read-only'));
        $this->app->instance(MediaStorage::class, $broken);

        [$code, $output] = runMediaCommand(new MediaStatusCommand, $this->app);

        expect($code)->toBe(1)->and($output)->toContain('The disk does not take a file: bucket is read-only');
    });
});

describe('pollora:media:files', function (): void {
    beforeEach(function (): void {
        $this->media = new WordPressMedia($this->storage, $this->config, null, $this->sizes);
        $this->app->instance(WordPressMedia::class, $this->media);
        Functions\when('get_post_meta')->justReturn('');
    });

    it('lists the files of a media and flags the ones missing from the disk', function (): void {
        $this->storage->write('2026/11/photo.jpg', 'x');
        Functions\when('wp_get_attachment_metadata')->justReturn(['file' => '2026/11/photo.jpg', 'sizes' => ['thumbnail' => ['file' => 'photo-150x150.jpg']]]);

        [$code, $output] = runMediaCommand(new MediaFilesCommand, $this->app, ['id' => '7']);

        expect($code)->toBe(0)
            ->and($output)->toContain('Media 7')
            ->and($output)->toContain('public')
            ->and($output)->toContain('2026/11/photo.jpg')
            ->and($output)->toContain('2026/11/photo-150x150.jpg')
            ->and($output)->toContain('not on the disk');
    });

    it('fails when no file is known for the media', function (): void {
        Functions\when('wp_get_attachment_metadata')->justReturn(false);

        [$code, $output] = runMediaCommand(new MediaFilesCommand, $this->app, ['id' => '8']);

        expect($code)->toBe(1)->and($output)->toContain('No file is known for this media.');
    });

    it('says a media is private when its meta says so', function (): void {
        Functions\when('get_post_meta')->justReturn('1');
        Functions\when('wp_get_attachment_metadata')->justReturn(false);

        [, $output] = runMediaCommand(new MediaFilesCommand, $this->app, ['id' => '9']);

        expect($output)->toContain('private');
    });
});

describe('pollora:media:thumbs:prune', function (): void {
    beforeEach(function (): void {
        $this->storage->write('2026/11/photo.jpg', 'x');
        $this->storage->write('2026/11/photo-oldsize-600x400-0123456789ab.webp', 'x');
    });

    it('only lists the stale files in a dry run', function (): void {
        [$code, $output] = runMediaCommand(new MediaThumbsPruneCommand, $this->app);

        expect($code)->toBe(0)
            ->and($output)->toContain('Would delete 2026/11/photo-oldsize-600x400-0123456789ab.webp')
            ->and($output)->toContain('Would delete 1 file(s).')
            ->and($output)->toContain('This was a dry run')
            ->and($this->storage->exists('2026/11/photo-oldsize-600x400-0123456789ab.webp'))->toBeTrue();
    });

    it('deletes the stale files with --execute and keeps the original', function (): void {
        [$code, $output] = runMediaCommand(new MediaThumbsPruneCommand, $this->app, ['--execute' => true]);

        expect($code)->toBe(0)
            ->and($output)->toContain('Deleted 2026/11/photo-oldsize-600x400-0123456789ab.webp')
            ->and($output)->not->toContain('dry run')
            ->and($this->storage->exists('2026/11/photo-oldsize-600x400-0123456789ab.webp'))->toBeFalse()
            ->and($this->storage->exists('2026/11/photo.jpg'))->toBeTrue();
    });
});

describe('pollora:media:thumbs:warm', function (): void {
    beforeEach(function (): void {
        $this->app->instance(WordPressMedia::class, new WordPressMedia($this->storage, $this->config, null, $this->sizes));
    });

    it('reports an empty library without failure', function (): void {
        Functions\when('get_posts')->justReturn([]);

        [$code, $output] = runMediaCommand(new MediaThumbsWarmCommand, $this->app);

        expect($code)->toBe(0)->and($output)->toContain('0 image(s) looked at; 0 file(s) made; 0 failed.');
    });

    it('fails with a message when the size asked for is not declared', function (): void {
        Functions\when('get_posts')->justReturn([]);

        [$code, $output] = runMediaCommand(new MediaThumbsWarmCommand, $this->app, ['--size' => 'nope']);

        expect($code)->toBe(1)->and($output)->toContain('The size "nope" is not declared.');
    });
});

describe('pollora:media:migrate', function (): void {
    beforeEach(function (): void {
        $this->from = $this->root.'/from';
        $this->to = $this->root.'/to';
        mkdir($this->from.'/2026/11', 0777, true);
        mkdir($this->to, 0777, true);
        file_put_contents($this->from.'/2026/11/photo.jpg', 'jpeg');
        file_put_contents($this->from.'/2026/11/doc.pdf', 'pdf');
        mkdir($this->from.'/woocommerce_uploads');
        file_put_contents($this->from.'/woocommerce_uploads/secret.zip', 'zip');

        $this->disks = Mockery::mock(Illuminate\Contracts\Filesystem\Factory::class);
        $this->disks->shouldReceive('disk')->with('old')->andReturn(new class($this->from)
        {
            public function __construct(private string $path) {}

            public function getDriver(): Filesystem
            {
                return new Filesystem(new LocalFilesystemAdapter($this->path));
            }
        });
        $this->disks->shouldReceive('disk')->with('new')->andReturn(new class($this->to)
        {
            public function __construct(private string $path) {}

            public function getDriver(): Filesystem
            {
                return new Filesystem(new LocalFilesystemAdapter($this->path));
            }
        });
        $this->app->instance(Illuminate\Contracts\Filesystem\Factory::class, $this->disks);
    });

    it('asks for a target disk when none is given or configured', function (): void {
        $this->app->instance('config', new Repository(['media' => ['disk' => null]]));

        [$code, $output] = runMediaCommand(new Pollora\Media\Infrastructure\Laravel\Console\MediaMigrateCommand, $this->app);

        expect($code)->toBe(1)->and($output)->toContain('Name the target disk with --to, or set media.disk.');
    });

    it('copies nothing in a dry run and says what it would copy', function (): void {
        [$code, $output] = runMediaCommand(new Pollora\Media\Infrastructure\Laravel\Console\MediaMigrateCommand, $this->app, ['--from' => 'old', '--to' => 'new']);

        expect($code)->toBe(0)
            ->and($output)->toContain('Would copy 3 file(s); 0 already there; 0 left out; 0 failed.')
            ->and($output)->toContain('This was a dry run')
            ->and(file_exists($this->to.'/2026/11/photo.jpg'))->toBeFalse();
    });

    it('copies the files with --execute, leaves out the excluded patterns and skips what is already there', function (): void {
        mkdir($this->to.'/2026/11', 0777, true);
        file_put_contents($this->to.'/2026/11/doc.pdf', 'pdf');

        [$code, $output] = runMediaCommand(new Pollora\Media\Infrastructure\Laravel\Console\MediaMigrateCommand, $this->app, [
            '--from' => 'old', '--to' => 'new', '--execute' => true, '--exclude' => ['woocommerce_uploads/*'],
        ]);

        expect($code)->toBe(0)
            ->and($output)->toContain('Copied 1 file(s); 1 already there; 1 left out; 0 failed.')
            ->and(file_get_contents($this->to.'/2026/11/photo.jpg'))->toBe('jpeg')
            ->and(file_exists($this->to.'/woocommerce_uploads/secret.zip'))->toBeFalse();
    });

    it('fails and names the file it could not copy', function (): void {
        $broken = Mockery::mock(League\Flysystem\FilesystemOperator::class);
        $broken->shouldReceive('fileExists')->andReturn(false);
        $broken->shouldReceive('write', 'writeStream')->andThrow(new RuntimeException('disk full'));
        $disks = Mockery::mock(Illuminate\Contracts\Filesystem\Factory::class);
        $disks->shouldReceive('disk')->with('old')->andReturn(new class($this->from)
        {
            public function __construct(private string $path) {}

            public function getDriver(): Filesystem
            {
                return new Filesystem(new LocalFilesystemAdapter($this->path));
            }
        });
        $disks->shouldReceive('disk')->with('new')->andReturn(new class($broken)
        {
            public function __construct(private object $driver) {}

            public function getDriver(): object
            {
                return $this->driver;
            }
        });
        $this->app->instance(Illuminate\Contracts\Filesystem\Factory::class, $disks);

        [$code, $output] = runMediaCommand(new Pollora\Media\Infrastructure\Laravel\Console\MediaMigrateCommand, $this->app, ['--from' => 'old', '--to' => 'new', '--execute' => true]);

        expect($code)->toBe(1)
            ->and($output)->toContain('Could not copy 2026/11/photo.jpg')
            ->and($output)->toContain('3 failed.');
    });

    it('counts the rows whose URL would be replaced in a dry run', function (): void {
        $GLOBALS['wpdb'] = new class
        {
            public string $posts = 'wp_posts';

            public string $postmeta = 'wp_postmeta';

            public string $termmeta = 'wp_termmeta';

            public string $usermeta = 'wp_usermeta';

            public string $options = 'wp_options';

            private int $calls = 0;

            public function esc_like(string $text): string
            {
                return $text;
            }

            public function prepare(string $query, mixed ...$args): string
            {
                return $query;
            }

            public function get_results(string $query): array
            {
                return $this->calls++ === 0
                    ? [(object) ['id' => 1, 'value' => '<img src="http://app.test/content/uploads/a.jpg">']]
                    : [];
            }

            public function update(): void {}
        };
        $this->app->instance('config', new Repository(['media' => ['disk' => 'cdn'], 'app' => ['url' => 'http://app.test/']]));

        try {
            [$code, $output] = runMediaCommand(new Pollora\Media\Infrastructure\Laravel\Console\MediaMigrateCommand, $this->app, ['--from' => 'old', '--to' => 'new', '--replace-urls' => true]);
        } finally {
            unset($GLOBALS['wpdb']);
        }

        expect($code)->toBe(0)
            ->and($output)->toContain('posts.post_content')
            ->and($output)->toContain('would replace 1 row(s)');
    });
});
