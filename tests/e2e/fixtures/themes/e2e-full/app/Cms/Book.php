<?php

declare(strict_types=1);

namespace Theme\E2eFull\Cms;

use Pollora\Attributes\PostType;
use Pollora\Attributes\PostType\HasArchive;
use Pollora\Attributes\PostType\PublicPostType;

/**
 * Has its own single and archive templates: single-e2e_book, archive-e2e_book.
 */
#[PostType('e2e_book', singular: 'E2E Book', plural: 'E2E Books')]
#[PublicPostType]
#[HasArchive]
class Book {}
