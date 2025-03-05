<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Post;

/**
 * Event fired when a new post is created.
 *
 * This event is triggered when a post transitions from auto-draft to draft status,
 * indicating that a new post has been created in the system.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class PostCreated extends PostEvent
{
} 