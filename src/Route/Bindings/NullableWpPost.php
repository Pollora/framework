<?php

declare(strict_types=1);

namespace Pollora\Route\Bindings;

use WP_Post;

/**
 * Represents a nullable WordPress post object with default values.
 *
 * This class provides a way to create a WP_Post object with all fields
 * initialized to safe default values, useful for situations where a post
 * might not exist but a post object structure is needed.
 */
class NullableWpPost
{
    /**
     * Create a new nullable WordPress post instance.
     *
     * @param int|null $ID Post ID (null represents no post)
     * @param int $post_author Author user ID
     * @param string $post_date Local post creation date
     * @param string $post_date_gmt GMT post creation date
     * @param string $content Post content
     * @param string $post_title Post title
     * @param string $post_excerpt Post excerpt
     * @param string $post_status Post status (draft, publish, etc.)
     * @param string $comment_status Comment status (open, closed)
     * @param string $ping_status Ping/trackback status
     * @param string $post_password Password to view post
     * @param string $post_name Post slug
     * @param string $to_ping URLs to ping
     * @param string $pinged URLs already pinged
     * @param string $post_modified Local modification date
     * @param string $post_modified_gmt GMT modification date
     * @param string $post_content_filtered Filtered post content
     * @param int $post_parent Parent post ID
     * @param string $guid Global unique identifier
     * @param int $menu_order Menu order for hierarchical post types
     * @param string $post_type Post type (post, page, etc.)
     * @param string $post_mime_type MIME type for attachments
     * @param int $comment_count Number of comments
     */
    public function __construct(
        public ?int $ID = null,
        public int $post_author = 0,
        public string $post_date = '0000-00-00 00:00:00',
        public string $post_date_gmt = '0000-00-00 00:00:00',
        public string $content = '',
        public string $post_title = '',
        public string $post_excerpt = '',
        public string $post_status = 'publish',
        public string $comment_status = 'open',
        public string $ping_status = 'open',
        public string $post_password = '',
        public string $post_name = '',
        public string $to_ping = '',
        public string $pinged = '',
        public string $post_modified = '0000-00-00 00:00:00',
        public string $post_modified_gmt = '0000-00-00 00:00:00',
        public string $post_content_filtered = '',
        public int $post_parent = 0,
        public string $guid = '',
        public int $menu_order = 0,
        public string $post_type = 'post',
        public string $post_mime_type = '',
        public int $comment_count = 0,
    ) {}

    /**
     * Convert the nullable post to a WordPress WP_Post object.
     *
     * Creates a new WP_Post instance using the current object's properties,
     * allowing this nullable post to be used wherever a WP_Post is expected.
     *
     * @return WP_Post A WordPress post object with the current properties
     */
    public function toWpPost(): WP_Post
    {
        return new WP_Post($this);
    }
}
