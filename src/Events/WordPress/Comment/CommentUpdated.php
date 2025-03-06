<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Comment;

/**
 * Event fired when a comment is updated.
 *
 * This event is triggered when a comment's content or metadata is modified.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class CommentUpdated extends CommentEvent {}
