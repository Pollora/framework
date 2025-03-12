<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Post;

/**
 * Event fired when a post is published.
 *
 * This event is triggered when a post transitions to the 'publish' status
 * from any status other than 'publish' or 'future'.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class PostPublished extends PostEvent {}
