<?php

declare(strict_types=1);

namespace Theme\E2eFull\Cms;

use Pollora\Attributes\Taxonomy;
use Pollora\Attributes\Taxonomy\PublicTaxonomy;

/**
 * The term e2e-rock has its own template (taxonomy-e2e_genre-e2e-rock); any other term
 * falls back to taxonomy-e2e_genre.
 */
#[Taxonomy('e2e_genre', singular: 'E2E Genre', plural: 'E2E Genres', objectType: 'e2e_book')]
#[PublicTaxonomy]
class Genre {}
