<?php

declare(strict_types=1);

namespace Pollora\Media\Infrastructure\Laravel\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Factory;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Pollora\Media\Application\Services\MediaMigrator;
use Pollora\Media\Domain\Exceptions\MediaDiskException;
use Pollora\Media\Domain\Models\MediaConfig;
use Pollora\Media\Infrastructure\Flysystem\FlysystemMediaStorage;
use Pollora\Media\Infrastructure\WordPress\UrlReplacement;

/**
 * Copies the media library from a disk to another. Nothing is written without
 * --execute; the source is never changed.
 */
#[Description('Copy the media files from a disk to another, and optionally replace their URLs in the database')]
#[Signature('pollora:media:migrate {--from= : Disk to copy from (default: the uploads folder WordPress uses without the module)} {--to= : Disk to copy to (default: media.disk)} {--execute : Copy the files; without it, only list what would be copied} {--exclude=* : A pattern of the paths to leave out, such as woocommerce_uploads/* (repeat the option for several)} {--replace-urls : Replace the old base URL with the public URL in the database} {--old-url= : The base URL to replace}')]
class MediaMigrateCommand extends Command
{
    public function handle(Factory $disks, MediaConfig $config): int
    {
        $to = (string) ($this->option('to') ?: config('media.disk'));

        if ($to === '') {
            $this->components->error('Name the target disk with --to, or set media.disk.');

            return self::FAILURE;
        }

        $fromDisk = $this->option('from');
        $source = is_string($fromDisk) && $fromDisk !== ''
            ? $this->storageOf($disks, $fromDisk)
            : new FlysystemMediaStorage(new Filesystem(new LocalFilesystemAdapter(public_path('content/uploads'))));

        $execute = (bool) $this->option('execute');
        $exclude = array_values(array_filter(array_map(strval(...), (array) $this->option('exclude'))));
        $report = (new MediaMigrator($source, $this->storageOf($disks, $to), $config->cacheControl))->migrate(! $execute, function (string $path, string $outcome): void {
            if ($outcome === 'fail') {
                $this->components->warn('Could not copy '.$path);
            }
        }, $exclude);

        $this->components->info(sprintf('%s %d file(s); %d already there; %d left out; %d failed.', $execute ? 'Copied' : 'Would copy', count($report->copied), count($report->skipped), count($report->excluded), count($report->failed)));

        if ($this->option('replace-urls')) {
            $old = (string) ($this->option('old-url') ?: rtrim((string) config('app.url'), '/').'/content/uploads');

            foreach ((new UrlReplacement($GLOBALS['wpdb']))->replace(rtrim($old, '/'), $config->url, ! $execute) as $where => $count) {
                $this->components->twoColumnDetail($where, sprintf('%s %d row(s)', $execute ? 'replaced' : 'would replace', $count));
            }
        }

        if (! $execute) {
            $this->components->info('This was a dry run. Add --execute to apply it.');
        }

        return $report->failed === [] ? self::SUCCESS : self::FAILURE;
    }

    private function storageOf(Factory $disks, string $name): FlysystemMediaStorage
    {
        $driver = $disks->disk($name)->getDriver();

        return new FlysystemMediaStorage($driver instanceof FilesystemOperator ? $driver : throw MediaDiskException::withoutFlysystemDriver($name));
    }
}
