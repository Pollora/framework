<?php

declare(strict_types=1);

namespace Pollora\Media\Domain\Events;

/**
 * A file of the media library is on the disk, relayed from the `pollora/media/stored` action.
 */
final readonly class MediaStored
{
    /**
     * @param  string  $path  Relative to the media library
     * @param  int  $size  In bytes
     */
    public function __construct(
        public string $path,
        public int $size,
    ) {}
}
