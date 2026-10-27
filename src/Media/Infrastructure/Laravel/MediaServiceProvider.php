<?php

declare(strict_types=1);

namespace Pollora\Media\Infrastructure\Laravel;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\FilesystemOperator;
use Pollora\Doctor\Infrastructure\Providers\DoctorServiceProvider;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Media\Application\Services\ImageSizes;
use Pollora\Media\Application\Services\ThumbnailLimits;
use Pollora\Media\Domain\Contracts\MediaStorage;
use Pollora\Media\Domain\Events\MediaDeleted;
use Pollora\Media\Domain\Events\MediaStored;
use Pollora\Media\Domain\Events\MediaVisibilityChanged;
use Pollora\Media\Domain\Exceptions\MediaDiskException;
use Pollora\Media\Domain\Models\MediaConfig;
use Pollora\Media\Domain\Models\ThumbnailDriverSettings;
use Pollora\Media\Infrastructure\Flysystem\FlysystemMediaStorage;
use Pollora\Media\Infrastructure\Laravel\Console\MediaFilesCommand;
use Pollora\Media\Infrastructure\Laravel\Console\MediaMigrateCommand;
use Pollora\Media\Infrastructure\Laravel\Console\MediaStatusCommand;
use Pollora\Media\Infrastructure\Laravel\Console\MediaThumbsExportCommand;
use Pollora\Media\Infrastructure\Laravel\Console\MediaThumbsPruneCommand;
use Pollora\Media\Infrastructure\Laravel\Console\MediaThumbsWarmCommand;
use Pollora\Media\Infrastructure\Laravel\Console\MediaVisibilityCommand;
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

        $this->app->singleton(MediaStorage::class, fn (Application $app): MediaStorage => new FlysystemMediaStorage(
            $this->filesystem($app),
            $this->temporaryUrls($app),
        ));

        $this->app->singleton(MediaCheck::class, fn (Application $app): MediaCheck => new MediaCheck(
            $this->diskName(),
            fn (): MediaStorage => $app->make(MediaStorage::class),
            fn (): MediaConfig => $app->make(MediaConfig::class),
            rtrim((string) (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : public_path('content')), '/').'/uploads',
            static fn (): ImageSizes => $app->make(ImageSizes::class),
            static fn (): array => $app->make(WordPressMedia::class)->thumbnailProblems(),
        ));

        // The file names of the sizes are signed with the application key.
        $this->app->singleton(ImageSizes::class, fn (Application $app): ImageSizes => new ImageSizes(
            static fn (): string => (string) $app->make('config')->get('app.key'),
        ));

        $this->app->singleton(WordPressMedia::class, fn (Application $app): WordPressMedia => new WordPressMedia(
            $app->make(MediaStorage::class),
            $app->make(MediaConfig::class),
            $app->make(Action::class),
            $app->make(ImageSizes::class),
            limits: $this->thumbnailLimits($app),
            thumbnails: $this->thumbnailDriver($app),
        ));
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../../config/media.php' => config_path('media.php')], 'media-config');

        // A check of pollora:doctor and Site Health
        $this->app->tag([MediaCheck::class], DoctorServiceProvider::CHECKS_TAG);

        if ($this->app->runningInConsole()) {
            $this->commands([MediaStatusCommand::class, MediaMigrateCommand::class, MediaFilesCommand::class, MediaVisibilityCommand::class, MediaThumbsWarmCommand::class, MediaThumbsPruneCommand::class, MediaThumbsExportCommand::class]);
        }

        if ($this->diskName() === null) {
            return;
        }

        $this->app->make(WordPressMedia::class)->register();
        $this->relayEvents();
    }

    /**
     * The WordPress actions of the module reach Laravel as events.
     */
    private function relayEvents(): void
    {
        $action = $this->app->make(Action::class);
        $events = $this->app->make('events');

        $action->add('pollora/media/stored', static fn (string $path, int $size) => $events->dispatch(new MediaStored($path, $size)), 10, 2);
        $action->add('pollora/media/deleted', static fn (int $attachmentId, array $paths) => $events->dispatch(new MediaDeleted($attachmentId, $paths)), 10, 2);
        $action->add('pollora/media/visibility_changed', static fn (int $attachmentId, bool $private) => $events->dispatch(new MediaVisibilityChanged($attachmentId, $private)), 10, 2);
    }

    /**
     * A signed URL from the Laravel disk, which knows more ways to sign than Flysystem does (a local disk
     * with `serve`, `buildTemporaryUrlsUsing()`); null when the disk cannot.
     *
     * @return Closure(string, DateTimeInterface): ?string
     */
    private function temporaryUrls(Application $app): Closure
    {
        $disk = $this->diskName();

        return static function (string $path, DateTimeInterface $expiresAt) use ($app, $disk): ?string {
            try {
                return $app->make(Factory::class)->disk((string) $disk)->temporaryUrl($path, $expiresAt);
            } catch (RuntimeException) {
                return null;
            }
        };
    }

    private function thumbnailLimits(Application $app): ThumbnailLimits
    {
        $config = $app->make('config');
        $defaults = new ThumbnailLimits;

        return new ThumbnailLimits(
            (int) $config->get('media.thumbnails.max_pixels', $defaults->maxPixels),
            (int) $config->get('media.thumbnails.max_bytes', $defaults->maxBytes),
            (int) $config->get('media.thumbnails.concurrency', $defaults->concurrency),
            (int) $config->get('media.thumbnails.wait', $defaults->wait),
        );
    }

    private function thumbnailDriver(Application $app): ThumbnailDriverSettings
    {
        $config = $app->make('config');
        $disk = $this->diskName();
        $source = (string) $config->get('media.thumbnails.imgproxy.source', 'url');
        $bucket = $source === 's3' && $disk !== null ? $config->get("filesystems.disks.{$disk}.bucket") : null;
        $optional = static fn (string $key): ?string => is_scalar($value = $config->get($key)) && (string) $value !== '' ? (string) $value : null;

        return new ThumbnailDriverSettings(
            (string) $config->get('media.thumbnails.driver', ThumbnailDriverSettings::POLLORA),
            $optional('media.thumbnails.bunny.pull_zone'),
            $optional('media.thumbnails.bunny.api_key'),
            $optional('media.thumbnails.imgproxy.url'),
            $optional('media.thumbnails.imgproxy.key'),
            $optional('media.thumbnails.imgproxy.salt'),
            (int) $config->get('media.thumbnails.imgproxy.signature_size', 32),
            $source,
            is_string($bucket) && $bucket !== '' ? $bucket : null,
        );
    }

    private function diskName(): ?string
    {
        $disk = $this->app->make('config')->get('media.disk');

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    private function filesystem(Application $app): FilesystemOperator
    {
        $disk = $this->diskName() ?? throw MediaDiskException::notConfigured();
        $driver = $app->make(Factory::class)->disk($disk)->getDriver();

        return $driver instanceof FilesystemOperator ? $driver : throw MediaDiskException::withoutFlysystemDriver($disk);
    }

    private function mediaConfig(Application $app): MediaConfig
    {
        $config = $app->make('config');
        $disk = $this->diskName();
        $url = $config->get('media.url') ?? ($disk === null ? null : $config->get("filesystems.disks.{$disk}.url"));

        if (! is_string($url) || $url === '') {
            throw MediaDiskException::withoutUrl((string) $disk);
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
