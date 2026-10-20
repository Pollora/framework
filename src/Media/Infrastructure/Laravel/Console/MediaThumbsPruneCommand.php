<?php

declare(strict_types=1);

namespace Pollora\Media\Infrastructure\Laravel\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Media\Application\Services\ImageSizes;
use Pollora\Media\Application\Services\SizePruner;
use Pollora\Media\Domain\Contracts\MediaStorage;

/**
 * Deletes the sized files that no declared size stands for any more. A dry run unless --execute.
 */
#[Description('Delete the image size files that no declared size stands for any more')]
#[Signature('pollora:media:thumbs:prune {--execute : Delete the files; without it, only list them}')]
class MediaThumbsPruneCommand extends Command
{
    public function handle(MediaStorage $storage, ImageSizes $sizes): int
    {
        $execute = (bool) $this->option('execute');
        $paths = (new SizePruner($storage, $sizes))->prune(! $execute);

        foreach ($paths as $path) {
            $this->line(($execute ? 'Deleted ' : 'Would delete ').$path);
        }

        $this->components->info(sprintf('%s %d file(s).', $execute ? 'Deleted' : 'Would delete', count($paths)));

        if (! $execute) {
            $this->components->info('This was a dry run. Add --execute to apply it.');
        }

        return self::SUCCESS;
    }
}
