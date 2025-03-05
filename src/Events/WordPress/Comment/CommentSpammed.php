<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Comment;

/**
 * Event fired when a comment is marked as spam.
 *
 * This event is triggered when a comment is marked as spam, either manually
 * or automatically by a spam detection service like Akismet.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class CommentSpammed extends CommentEvent
{
} 