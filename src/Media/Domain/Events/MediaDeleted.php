<?php

declare(strict_types=1);

namespace Pollora\Media\Domain\Events;

/**
 * A media and its files were deleted, relayed from the `pollora/media/deleted` action.
 */
final readonly class MediaDeleted
{
    /**
     * @param  list<string>  $paths  The files of the media, relative to the media library
     */
    public function __construct(
        public int $attachmentId,
        public array $paths,
    ) {}
}
