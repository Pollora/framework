<?php

declare(strict_types=1);

namespace Pollora\Media\Infrastructure\Laravel;

use Closure;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Media\Application\Services\ImageSizes;
use Pollora\Media\Application\Services\MediaStatus;
use Pollora\Media\Domain\Contracts\MediaStorage;
use Pollora\Media\Domain\Enums\OutputFormat;
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
     * Folders of the uploads that hold private files (protected downloads, logs, form attachments, backups).
     * The media disk serves every file to anyone who knows the URL, so they must stay where the server protects them.
     */
    private const array PRIVATE_FOLDERS = ['woocommerce_uploads', 'wc-logs', 'gravity_forms', 'wpforms', 'updraft', 'ai1wm-backups'];

    /**
     * @param  Closure(): MediaStorage  $storage  Resolved only when a disk is set
     * @param  Closure(): MediaConfig  $config
     * @param  (Closure(): ImageSizes)|null  $sizes  The declared image sizes
     * @param  (Closure(): list<string>)|null  $thumbnailProblems  What keeps the sizes from being served by the driver that is set
     */
    public function __construct(
        private ?string $disk,
        private Closure $storage,
        private Closure $config,
        private string $localUploads,
        private ?Closure $sizes = null,
        private ?Closure $thumbnailProblems = null,
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

        $exposed = $this->privateFoldersOnDisk($storage);

        if ($exposed !== []) {
            return CheckResult::warning(
                sprintf('%d folder(s) of private files are on the media disk, where any visitor who knows a URL can read them: %s.', count($exposed), implode(', ', $exposed)),
                array_map(static fn (string $folder): string => $folder.'/ is served publicly', $exposed),
                'Delete them from the disk, and migrate with php artisan pollora:media:migrate --exclude='.$exposed[0].'/* so they stay where the server protects them',
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

        $sizeProblems = [...$this->sizeProblems(), ...($this->thumbnailProblems instanceof Closure ? ($this->thumbnailProblems)() : [])];

        if ($sizeProblems !== []) {
            return CheckResult::warning(
                sprintf('%d problem(s) with the declared image sizes.', count($sizeProblems)),
                $sizeProblems,
                'Fix the declaration (cover(), scale() or contain() with the dimensions it needs), choose a format the image editor of this server can write (WebP: GD and Imagick; AVIF depends on the server), or complete media.thumbnails.bunny / media.thumbnails.imgproxy',
            );
        }

        $rewrite = $this->rewriteInUploads();

        if ($rewrite !== null) {
            return CheckResult::warning(
                'A .htaccess in the uploads folder stops the sizes made on demand from reaching PHP.',
                [$rewrite.' contains RewriteEngine On: Apache then ignores the rules of the folders above it'],
                'Remove RewriteEngine On from that file (a security plugin usually adds it), or declare the sizes you use eager()',
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
     * Declarations that could not make a size, and sizes in a format this server's image editor cannot write.
     *
     * @return list<string>
     */
    private function sizeProblems(): array
    {
        if (! $this->sizes instanceof Closure) {
            return [];
        }

        $sizes = ($this->sizes)();
        $problems = $sizes->refusals();

        if (function_exists('wp_image_editor_supports')) {
            foreach ($sizes->all() as $spec) {
                $mime = match ($spec->format) {
                    OutputFormat::Webp => 'image/webp',
                    OutputFormat::Avif => 'image/avif',
                    OutputFormat::Original => null,
                };

                if ($mime !== null && ! \wp_image_editor_supports(['mime_type' => $mime])) {
                    $problems[] = sprintf('The size "%s" is written in %s, which the image editor of this server cannot write.', $spec->name, $mime);
                }
            }
        }

        return $problems;
    }

    /**
     * The `.htaccess` of the uploads folder that restarts Apache's rewriting, if there is one: it cuts the fallback for missing sizes.
     */
    private function rewriteInUploads(): ?string
    {
        $file = $this->localUploads.'/.htaccess';

        return is_file($file) && stripos((string) file_get_contents($file), 'RewriteEngine On') !== false ? $file : null;
    }

    /**
     * The folders of private files that hold something on the media disk.
     *
     * @return list<string>
     */
    private function privateFoldersOnDisk(MediaStorage $storage): array
    {
        $found = [];

        foreach (self::PRIVATE_FOLDERS as $folder) {
            try {
                if ($storage->list($folder) !== []) {
                    $found[] = $folder;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $found;
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
     * A file of a folder that is kept local on purpose, so it is not a file the migration forgot.
     */
    private function isPrivateFolder(string $relative): bool
    {
        foreach (self::PRIVATE_FOLDERS as $folder) {
            if (str_starts_with($relative, $folder.'/')) {
                return true;
            }
        }

        return false;
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

            if ($this->isPrivateFolder($relative)) {
                continue;
            }

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
