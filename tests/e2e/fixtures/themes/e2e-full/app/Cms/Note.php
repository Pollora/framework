<?php

declare(strict_types=1);

namespace Theme\E2eFull\Cms;

use Pollora\Attributes\PostType;
use Pollora\Attributes\PostType\HasArchive;
use Pollora\Attributes\PostType\PublicPostType;

/**
 * Has no template of its own: its archive falls back to the generic archive template.
 */
#[PostType('e2e_note', singular: 'E2E Note', plural: 'E2E Notes')]
#[PublicPostType]
#[HasArchive]
class Note {}
