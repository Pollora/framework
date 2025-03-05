<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Post;

use Pollora\Events\WordPress\AbstractEventDispatcher;
use WP_Post;

/**
 * Event dispatcher for WordPress post-related events.
 *
 * This class handles the dispatching of Laravel events for WordPress post actions
 * such as creation, update, deletion, and status changes.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class PostEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'transition_post_status',
        'deleted_post',
    ];

    /**
     * Handle post status transition.
     *
     * @param  string  $new_status  New post status
     * @param  string  $old_status  Old post status
     * @param  WP_Post  $post  Post object
     */
    public function handleTransitionPostStatus(string $new_status, string $old_status, WP_Post $post): void
    {
        // Skip auto-drafts and revisions
        if (in_array($new_status, ['auto-draft', 'inherit']) || in_array($post->post_type, ['revision'])) {
            return;
        }

        // Handle different status transitions
        if ($old_status === 'auto-draft' && $new_status === 'draft') {
            $this->dispatch(PostCreated::class, [$post]);
        } elseif ($old_status === 'trash' && $new_status !== 'trash') {
            $this->dispatch(PostRestored::class, [$post]);
        } elseif ($new_status === 'trash') {
            $this->dispatch(PostTrashed::class, [$post]);
        } elseif ($new_status === 'publish' && ! in_array($old_status, ['publish', 'future'])) {
            $this->dispatch(PostPublished::class, [$post]);
        } else {
            $this->dispatch(PostUpdated::class, [$post, $old_status, $new_status]);
        }
    }

    /**
     * Handle post deletion.
     *
     * @param  int  $post_id  The ID of the deleted post
     */
    public function handleDeletedPost(int $post_id): void
    {
        $post = get_post($post_id);

        if (! $post || in_array($post->post_type, ['revision'])) {
            return;
        }

        $this->dispatch(PostDeleted::class, [$post]);
    }
}
