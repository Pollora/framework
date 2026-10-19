<?php

declare(strict_types=1);

namespace Pollora\Media\Infrastructure\Laravel\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Pollora\Media\Domain\Contracts\MediaStorage;
use Pollora\Media\Infrastructure\WordPress\SizeWarmer;
use Pollora\Media\Infrastructure\WordPress\WordPressMedia;

/**
 * Makes the missing files of the declared sizes for the images already in the library.
 */
#[Description('Make the missing files of the declared image sizes for the images already in the library')]
#[Signature('pollora:media:thumbs:warm {--size= : Only this declared size} {--since= : Only the images uploaded after this date}')]
class MediaThumbsWarmCommand extends Command
{
    public function handle(WordPressMedia $media, MediaStorage $storage): int
    {
        try {
            $report = (new SizeWarmer($media->declaredSizes(), $storage))->warm(
                $this->option('size') ?: null,
                $this->option('since') ?: null,
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->components->error($invalidArgumentException->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('%d image(s) looked at; %d file(s) made; %d failed.', $report['checked'], $report['made'], $report['failed']));

        return $report['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
