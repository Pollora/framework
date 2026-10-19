<?php

declare(strict_types=1);

namespace Pollora\Media\Infrastructure\Laravel;

use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\FilesystemOperator;
use Pollora\Doctor\Infrastructure\Providers\DoctorServiceProvider;
use Pollora\Media\Domain\Contracts\MediaStorage;
use Pollora\Media\Domain\Models\MediaConfig;
use Pollora\Media\Infrastructure\Flysystem\FlysystemMediaStorage;
use Pollora\Media\Infrastructure\Laravel\Console\MediaMigrateCommand;
use Pollora\Media\Infrastructure\Laravel\Console\MediaStatusCommand;
use Pollora\Media\Infrastructure\WordPress\WordPressMedia;
use RuntimeException;

/**
 * The media library on a Laravel disk: `media.disk` names a disk of
 * `config/filesystems.php`, `media.url` the URL its files are served from.
 * The package does the rest, with the Flysystem driver of that disk.
 *
 * With no `media.disk`, the module does nothing and WordPress keeps its
 * uploads folder.
 */
class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/media.php', 'media');

        $this->app->singleton(MediaConfig::class, fn (Application $app): MediaConfig => $this->mediaConfig($app));

        $this->app->singleton(MediaStorage::class, fn (Application $app): MediaStorage => new FlysystemMediaStorage($this->filesystem($app)));

        $this->app->singleton(MediaCheck::class, fn (Application $app): MediaCheck => new MediaCheck(
            $this->diskName(),
            fn (): MediaStorage => $app->make(MediaStorage::class),
            fn (): MediaConfig => $app->make(MediaConfig::class),
            rtrim((string) (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : public_path('content')), '/').'/uploads',
        ));

        $this->app->singleton(WordPressMedia::class, fn (Application $app): WordPressMedia => new WordPressMedia(
            $app->make(MediaStorage::class),
            $app->make(MediaConfig::class),
        ));
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../../config/media.php' => config_path('media.php')], 'media-config');

        // A check of pollora:doctor and Site Health
        $this->app->tag([MediaCheck::class], DoctorServiceProvider::CHECKS_TAG);

        if ($this->app->runningInConsole()) {
            $this->commands([MediaStatusCommand::class, MediaMigrateCommand::class]);
        }

        if ($this->diskName() === null) {
            return;
        }

        $this->app->make(WordPressMedia::class)->register();
    }

    private function diskName(): ?string
    {
        $disk = $this->app->make('config')->get('media.disk');

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    private function filesystem(Application $app): FilesystemOperator
    {
        $disk = $this->diskName() ?? throw new RuntimeException('config/media.php has no "disk": the media module is off.');
        $driver = $app->make(Factory::class)->disk($disk)->getDriver();

        return $driver instanceof FilesystemOperator ? $driver : throw new RuntimeException(sprintf('The disk "%s" has no Flysystem driver.', $disk));
    }

    private function mediaConfig(Application $app): MediaConfig
    {
        $config = $app->make('config');
        $disk = $this->diskName();
        $url = $config->get('media.url') ?? ($disk === null ? null : $config->get("filesystems.disks.{$disk}.url"));

        if (! is_string($url) || $url === '') {
            throw new RuntimeException(sprintf('Set media.url (MEDIA_URL), or the "url" of the disk "%s": a media URL is built from it.', (string) $disk));
        }

        return new MediaConfig(
            $url,
            array_values((array) $config->get('media.legacy_urls', [])),
            (string) $config->get('media.cache_control', 'public, max-age=31536000, immutable'),
            (string) $config->get('media.private.expiry', '+6 hours'),
            (bool) $config->get('media.async_thumbnails', false),
        );
    }
}
