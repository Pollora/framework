<?php

declare(strict_types=1);

namespace Pollora\Media\Infrastructure\Laravel\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Media\Application\Services\MediaStatus;
use Pollora\Media\Domain\Contracts\MediaStorage;
use Pollora\Media\Domain\Models\MediaConfig;

/**
 * Checks the media storage: the disk, the public URL, and that the disk takes a file.
 */
#[Description('Check the media storage: disk, public URL, and a test write')]
#[Signature('pollora:media:status')]
class MediaStatusCommand extends Command
{
    public function handle(): int
    {
        $disk = config('media.disk');

        if (! is_string($disk) || $disk === '') {
            $this->components->info('The media module is off (media.disk is not set): WordPress keeps its uploads folder.');

            return self::SUCCESS;
        }

        $status = (new MediaStatus(app(MediaStorage::class), app(MediaConfig::class)))->check();

        $this->components->twoColumnDetail('Disk', $disk);
        $this->components->twoColumnDetail('Public URL', $status['url']);

        if (! $status['writable']) {
            $this->components->error('The disk does not take a file: '.$status['error']);

            return self::FAILURE;
        }

        $this->components->info('The disk takes a file, gives it back and deletes it.');

        return self::SUCCESS;
    }
}
