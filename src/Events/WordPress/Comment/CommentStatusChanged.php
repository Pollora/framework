<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Comment;

/**
 * Event fired when a comment's status is changed.
 *
 * This event is triggered when a comment's status changes (e.g., from pending to approved,
 * approved to spam, etc.). It includes both the old and new status for tracking the transition.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class CommentStatusChanged extends CommentEvent
{
    /**
     * Constructor.
     */
    public function __construct(
        \WP_Comment $comment,
        public readonly string $oldStatus,
        public readonly string $newStatus
    ) {
        parent::__construct($comment);
    }
} 