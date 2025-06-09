<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Post;

/**
 * Event fired when a post is restored from trash.
 *
 * This event is triggered when a post transitions from 'trash' status
 * to any other status.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class PostRestored extends PostEvent {}
