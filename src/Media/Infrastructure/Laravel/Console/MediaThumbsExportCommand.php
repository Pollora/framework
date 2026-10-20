<?php

declare(strict_types=1);

namespace Pollora\Media\Infrastructure\Laravel\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Pollora\Media\Infrastructure\WordPress\WordPressMedia;
use RuntimeException;

/**
 * Gives the service that makes the sizes what it needs to serve the declared ones: the image classes of a
 * Bunny pull zone, the presets of imgproxy.
 *
 * Bunny: the classes are merged by name (the ones you made are kept, nothing is deleted) and written with
 * --execute; the setting that forces classes is never changed. imgproxy has no API for presets: the
 * configuration to give it is printed, without the key or the salt.
 */
#[Description('Export the declared image sizes as Bunny image classes or imgproxy presets')]
#[Signature('pollora:media:thumbs:export {--driver= : bunny or imgproxy (default: the driver of the sizes)} {--execute : Bunny only: write the classes to the pull zone (a dry run otherwise)}')]
class MediaThumbsExportCommand extends Command
{
    public function handle(WordPressMedia $media): int
    {
        $exporter = $media->thumbnailExporter();
        $driver = (string) ($this->option('driver') ?: $exporter->configuredDriver());
        $execute = (bool) $this->option('execute');

        try {
            $report = $exporter->export($driver, $execute);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($report->lines as $line) {
            $this->line($line);
        }

        foreach (array_unique($report->warnings) as $warning) {
            $this->components->warn($warning);
        }

        if ($driver === 'bunny' && ! $execute && ($report->created !== [] || $report->updated !== [])) {
            $this->components->info('This was a dry run. Add --execute to write the classes.');
        }

        if ($report->applied) {
            $this->components->info(sprintf('%d class(es) created, %d updated.', count($report->created), count($report->updated)));
        }

        return self::SUCCESS;
    }
}
