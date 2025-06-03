<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Comment;

use Pollora\Events\WordPress\AbstractEventDispatcher;
use WP_Comment;

/**
 * Event dispatcher for WordPress comment-related events.
 *
 * This class handles the dispatching of Laravel events for WordPress comment actions
 * such as creation, update, deletion, status changes, and spam management.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class CommentEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'wp_insert_comment',
        'edit_comment',
        'transition_comment_status',
        'trash_comment',
        'untrash_comment',
        'spam_comment',
        'unspam_comment',
        'delete_comment',
    ];

    /**
     * Handle new comment creation.
     *
     * @param  int  $commentId  The ID of the new comment
     * @param  WP_Comment  $comment  The comment object
     */
    public function handleWpInsertComment(int $commentId, WP_Comment $comment): void
    {
        if (in_array($comment->comment_type, ['pingback', 'trackback'])) {
            return;
        }

        $this->dispatch(CommentCreated::class, [$comment]);
    }

    /**
     * Handle comment editing.
     *
     * @param  int  $commentId  The ID of the edited comment
     */
    public function handleEditComment(int $commentId): void
    {
        $comment = get_comment($commentId);

        if (! $comment || in_array($comment->comment_type, ['pingback', 'trackback'])) {
            return;
        }

        $this->dispatch(CommentUpdated::class, [$comment]);
    }

    /**
     * Handle comment status transitions.
     *
     * @param  string  $newStatus  New comment status
     * @param  string  $oldStatus  Old comment status
     * @param  WP_Comment  $comment  The comment object
     */
    public function handleTransitionCommentStatus(string $newStatus, string $oldStatus, WP_Comment $comment): void
    {
        if (in_array($comment->comment_type, ['pingback', 'trackback'])) {
            return;
        }

        $this->dispatch(CommentStatusChanged::class, [$comment, $oldStatus, $newStatus]);
    }

    /**
     * Handle comment trashing.
     *
     * @param  int  $commentId  The ID of the trashed comment
     */
    public function handleTrashComment(int $commentId): void
    {
        $comment = get_comment($commentId);

        if (! $comment || in_array($comment->comment_type, ['pingback', 'trackback'])) {
            return;
        }

        $this->dispatch(CommentTrashed::class, [$comment]);
    }

    /**
     * Handle comment restoration from trash.
     *
     * @param  int  $commentId  The ID of the restored comment
     */
    public function handleUntrashComment(int $commentId): void
    {
        $comment = get_comment($commentId);

        if (! $comment || in_array($comment->comment_type, ['pingback', 'trackback'])) {
            return;
        }

        $this->dispatch(CommentRestored::class, [$comment]);
    }

    /**
     * Handle comment marked as spam.
     *
     * @param  int  $commentId  The ID of the spammed comment
     */
    public function handleSpamComment(int $commentId): void
    {
        $comment = get_comment($commentId);

        if (! $comment || in_array($comment->comment_type, ['pingback', 'trackback'])) {
            return;
        }

        $this->dispatch(CommentSpammed::class, [$comment]);
    }

    /**
     * Handle comment unmarked as spam.
     *
     * @param  int  $commentId  The ID of the unspammed comment
     */
    public function handleUnspamComment(int $commentId): void
    {
        $comment = get_comment($commentId);

        if (! $comment || in_array($comment->comment_type, ['pingback', 'trackback'])) {
            return;
        }

        $this->dispatch(CommentStatusChanged::class, [$comment, 'spam', 'approved']);
    }

    /**
     * Handle comment permanent deletion.
     *
     * @param  int  $commentId  The ID of the deleted comment
     */
    public function handleDeleteComment(int $commentId): void
    {
        $comment = get_comment($commentId);

        if (! $comment || in_array($comment->comment_type, ['pingback', 'trackback'])) {
            return;
        }

        $this->dispatch(CommentDeleted::class, [$comment]);
    }
}
