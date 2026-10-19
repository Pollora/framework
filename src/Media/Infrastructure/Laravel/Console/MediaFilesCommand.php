<?php

declare(strict_types=1);

namespace Pollora\Media\Infrastructure\Laravel\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Media\Domain\Contracts\MediaStorage;
use Pollora\Media\Infrastructure\WordPress\AttachmentFiles;
use Pollora\Media\Infrastructure\WordPress\WordPressMedia;

/**
 * Lists the files of a media: the original, each size, and the files a plugin declared with it.
 */
#[Description('List the files of a media: original, sizes and declared files')]
#[Signature('pollora:media:files {id : The attachment ID}')]
class MediaFilesCommand extends Command
{
    public function handle(MediaStorage $storage, WordPressMedia $media): int
    {
        $id = (int) $this->argument('id');

        $this->components->twoColumnDetail('Media '.$id, $media->privateMedia()->isPrivate($id) ? 'private' : 'public');

        $files = (new AttachmentFiles)->for($id);

        if ($files === []) {
            $this->components->warn('No file is known for this media.');

            return self::FAILURE;
        }

        foreach ($files as $file) {
            $this->components->twoColumnDetail($file, $storage->exists($file) ? '' : '<fg=red>not on the disk</>');
        }

        return self::SUCCESS;
    }
}
