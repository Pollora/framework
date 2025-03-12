<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Taxonomy;

use Pollora\Events\WordPress\AbstractEventDispatcher;
use WP_Term;

/**
 * Event dispatcher for WordPress taxonomy-related events.
 *
 * This class handles the dispatching of Laravel events for WordPress taxonomy actions
 * such as term creation, update, and deletion.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class TaxonomyEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'created_term',
        'delete_term',
        'edit_term',
        'edited_term',
    ];

    /**
     * Cache term values before update
     */
    protected ?WP_Term $cachedTermBeforeUpdate = null;

    /**
     * Handle term creation.
     *
     * @param  int  $termId  Term ID
     * @param  int  $ttId  Term taxonomy ID
     * @param  string  $taxonomy  Taxonomy name
     */
    public function handleCreatedTerm(int $termId, int $ttId, string $taxonomy): void
    {
        if (in_array($taxonomy, ['nav_menu'])) {
            return;
        }

        $term = get_term($termId, $taxonomy);
        if (! $term || is_wp_error($term)) {
            return;
        }

        $this->dispatch(TermCreated::class, [$term]);
    }

    /**
     * Handle term deletion.
     *
     * @param  int  $termId  Term ID
     * @param  int  $ttId  Term taxonomy ID
     * @param  string  $taxonomy  Taxonomy name
     * @param  WP_Term  $deletedTerm  Deleted term object
     */
    public function handleDeleteTerm(int $termId, int $ttId, string $taxonomy, WP_Term $deletedTerm): void
    {
        if (in_array($taxonomy, ['nav_menu'])) {
            return;
        }

        $this->dispatch(TermDeleted::class, [$deletedTerm]);
    }

    /**
     * Cache term before update.
     *
     * @param  int  $termId  Term ID
     * @param  int  $ttId  Term taxonomy ID
     * @param  string  $taxonomy  Taxonomy name
     */
    public function handleEditTerm(int $termId, int $ttId, string $taxonomy): void
    {
        $term = get_term($termId, $taxonomy);
        if (! $term || is_wp_error($term)) {
            return;
        }

        $this->cachedTermBeforeUpdate = $term;
    }

    /**
     * Handle term update.
     *
     * @param  int  $termId  Term ID
     * @param  int  $ttId  Term taxonomy ID
     * @param  string  $taxonomy  Taxonomy name
     */
    public function handleEditedTerm(int $termId, int $ttId, string $taxonomy): void
    {
        if (in_array($taxonomy, ['nav_menu'])) {
            return;
        }

        $term = get_term($termId, $taxonomy);
        if (! $term || is_wp_error($term)) {
            return;
        }

        $this->dispatch(TermUpdated::class, [
            $term,
            $this->cachedTermBeforeUpdate,
        ]);

        $this->cachedTermBeforeUpdate = null;
    }
}
