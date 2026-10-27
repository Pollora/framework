<?php

declare(strict_types=1);

namespace Pollora\Media\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when the disk the media module is configured with cannot serve it.
 */
final class MediaDiskException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('config/media.php has no "disk": the media module is off. Set "disk" to a disk of config/filesystems.php.');
    }

    /**
     * @param  string  $disk  The configured disk
     */
    public static function withoutFlysystemDriver(string $disk): self
    {
        return new self(sprintf('The disk "%s" has no Flysystem driver. Use a disk built on Flysystem (local, s3, ftp, sftp), not a custom filesystem.', $disk));
    }

    /**
     * @param  string  $disk  The configured disk, or an empty string when none is
     */
    public static function withoutUrl(string $disk): self
    {
        return new self(sprintf('Set media.url (MEDIA_URL), or the "url" of the disk "%s": a media URL is built from it.', $disk));
    }
}
