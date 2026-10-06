<?php

declare(strict_types=1);

namespace Plugin\E2eFeatures;

use Carbon\CarbonImmutable;
use Pollora\Attributes\Meta;
use Pollora\Attributes\PostType;
use Pollora\Attributes\PostType\HasArchive;
use Pollora\Attributes\PostType\PublicPostType;
use Pollora\Attributes\PostType\ShowInRest;

/**
 * A post type declared by a plugin, not by the theme, with typed meta the
 * block bindings read.
 */
#[PostType('e2e_event', singular: 'E2E Event', plural: 'E2E Events')]
#[PublicPostType]
#[HasArchive]
#[ShowInRest]
class Event
{
    #[Meta(showInRest: true)]
    public ?CarbonImmutable $startsAt = null;

    #[Meta(showInRest: true)]
    public int $capacity = 0;

    #[Meta(showInRest: true)]
    public bool $soldOut = false;

    /** Kept out of REST, so no block binding may show it */
    #[Meta]
    public string $backstageCode = '';
}
