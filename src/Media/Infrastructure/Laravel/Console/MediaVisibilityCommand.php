<?php

declare(strict_types=1);

namespace Pollora\Media\Infrastructure\Laravel\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Media\Infrastructure\WordPress\WordPressMedia;

/**
 * Makes a media private or public: every file of it changes on the disk.
 */
#[Description('Make a media private or public: every file of it changes on the disk')]
#[Signature('pollora:media:visibility {id : The attachment ID} {--private : Make it private (a signed URL that expires)} {--public : Make it public (the default)}')]
class MediaVisibilityCommand extends Command
{
    public function handle(WordPressMedia $media): int
    {
        $private = (bool) $this->option('private');
        $report = $media->privateMedia()->setPrivate((int) $this->argument('id'), $private);

        $this->components->info(sprintf('%d file(s) made %s.', count($report['changed']), $private ? 'private' : 'public'));

        foreach ($report['failed'] as $file) {
            $this->components->warn('Not changed: '.$file);
        }

        return $report['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
