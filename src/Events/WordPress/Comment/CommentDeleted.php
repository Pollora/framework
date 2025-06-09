<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Comment;

/**
 * Event fired when a comment is permanently deleted.
 *
 * This event is triggered when a comment is permanently removed from the database,
 * typically after being deleted from the trash.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class CommentDeleted extends CommentEvent {}
