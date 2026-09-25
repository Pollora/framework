<?php

declare(strict_types=1);

namespace Plugin\E2eFeatures;

use Pollora\Attributes\PostType;
use Pollora\Attributes\PostType\HasArchive;
use Pollora\Attributes\PostType\PublicPostType;
use Pollora\Attributes\PostType\ShowInRest;

/**
 * A post type declared by a plugin, not by the theme.
 */
#[PostType('e2e_event', singular: 'E2E Event', plural: 'E2E Events')]
#[PublicPostType]
#[HasArchive]
#[ShowInRest]
class Event {}
