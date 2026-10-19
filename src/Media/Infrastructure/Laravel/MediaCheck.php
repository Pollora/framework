<?php

declare(strict_types=1);

namespace Pollora\Media\Infrastructure\Laravel;

use Closure;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Media\Application\Services\MediaStatus;
use Pollora\Media\Domain\Contracts\MediaStorage;
use Pollora\Media\Domain\Models\MediaConfig;
use Throwable;

/**
 * The media disk takes a file, and no media was left behind in the local
 * uploads folder.
 *
 * A plugin that writes under `WP_CONTENT_DIR/uploads` by hand ignores the
 * `upload_dir` filter: its files stay local, and nothing shows an error until
 * a page asks the media domain for them.
 */
final readonly class MediaCheck implements CheckInterface
{
    /** Local files compared with the disk, at most: each comparison asks the disk. */
    private const int SAMPLE = 200;

    /**
     * @param  Closure(): MediaStorage  $storage  Resolved only when a disk is set
     * @param  Closure(): MediaConfig  $config
     */
    public function __construct(
        private ?string $disk,
        private Closure $storage,
        private Closure $config,
        private string $localUploads,
    ) {}

    public function id(): string
    {
        return 'media';
    }

    public function label(): string
    {
        return 'Media storage';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        if ($this->disk === null) {
            return CheckResult::skipped('media.disk is not set: WordPress keeps its uploads folder.');
        }

        $storage = ($this->storage)();
        $config = ($this->config)();
        $status = (new MediaStatus($storage, $config))->check();

        if (! $status['writable']) {
            return CheckResult::error(
                sprintf('The media disk "%s" does not take a file.', $this->disk),
                [(string) $status['error']],
                'Check the disk in config/filesystems.php (credentials, bucket, endpoint), then run php artisan pollora:media:status',
            );
        }

        $unsigned = $this->privateMediaWithoutSignature($storage);

        if ($unsigned !== null) {
            return CheckResult::warning(
                sprintf('The disk "%s" cannot sign URLs, and %d media are private: their plain URL is served.', $this->disk, $unsigned),
                [],
                'A private media needs a disk that signs URLs (S3 and compatible services), or a signature of your own with the pollora/media/signed_url filter',
            );
        }

        $left = $this->leftBehind($storage);

        if ($left !== []) {
            return CheckResult::warning(
                sprintf('%d file(s) of the local uploads folder are not on the media disk.', count($left)),
                array_map(static fn (string $path): string => $path.' is only local', array_slice($left, 0, 5)),
                'Copy them with php artisan pollora:media:migrate --from=<a disk on the local uploads folder> --execute, or find the plugin that writes under uploads by hand',
            );
        }

        return CheckResult::ok(sprintf('The disk "%s" takes a file; media are served from %s.', $this->disk, $config->url));
    }

    /**
     * How many media are private, when the disk cannot sign a URL for them; null when it can, or when none is.
     */
    private function privateMediaWithoutSignature(MediaStorage $storage): ?int
    {
        if (! function_exists('get_posts') || $storage->temporaryUrl('.pollora-media-probe', new \DateTimeImmutable('+1 minute')) !== null) {
            return null;
        }

        $private = \get_posts(['post_type' => 'attachment', 'post_status' => 'any', 'meta_key' => '_pollora_media_private', 'meta_value' => '1', 'fields' => 'ids', 'numberposts' => 100]);

        return $private === [] ? null : count($private);
    }

    /**
     * @return list<string>
     */
    private function leftBehind(MediaStorage $storage): array
    {
        if (! is_dir($this->localUploads)) {
            return [];
        }

        $left = [];
        $seen = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->localUploads, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if (++$seen > self::SAMPLE) {
                break;
            }

            $relative = ltrim(substr($file->getPathname(), strlen($this->localUploads)), '/');

            try {
                if (! $storage->exists($relative)) {
                    $left[] = $relative;
                }
            } catch (Throwable) {
                return $left;
            }
        }

        return $left;
    }
}
