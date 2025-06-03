<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Blog;

/**
 * Event fired when a blog is unarchived.
 *
 * This event is triggered when a blog is restored from archived status in a WordPress multisite network.
 * The blog becomes accessible to visitors again.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class BlogUnarchived extends BlogEvent {}
