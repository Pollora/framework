<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Taxonomy;

use WP_Term;

/**
 * Event fired when a term is updated.
 *
 * This event is triggered when a term's data is modified in any taxonomy,
 * except for excluded taxonomies like nav_menu.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class TermUpdated extends TaxonomyEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        WP_Term $term,
        public readonly ?WP_Term $previousTerm
    ) {
        parent::__construct($term);
    }
}
