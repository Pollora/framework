<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Models;

/**
 * Domain entity representing a nullable post with default values.
 *
 * This class provides a way to create a post object with all fields
 * initialized to safe default values, independent of WordPress implementation.
 */
class NullablePostEntity
{
    /**
     * Create a new nullable post entity.
     *
     * @param  int|null  $id  Post ID (null represents no post)
     * @param  int  $authorId  Author user ID
     * @param  string  $date  Local post creation date
     * @param  string  $dateGmt  GMT post creation date
     * @param  string  $content  Post content
     * @param  string  $title  Post title
     * @param  string  $excerpt  Post excerpt
     * @param  string  $status  Post status (draft, publish, etc.)
     * @param  string  $commentStatus  Comment status (open, closed)
     * @param  string  $pingStatus  Ping/trackback status
     * @param  string  $password  Password to view post
     * @param  string  $slug  Post slug
     * @param  string  $toPing  URLs to ping
     * @param  string  $pinged  URLs already pinged
     * @param  string  $modified  Local modification date
     * @param  string  $modifiedGmt  GMT modification date
     * @param  string  $contentFiltered  Filtered post content
     * @param  int  $parentId  Parent post ID
     * @param  string  $guid  Global unique identifier
     * @param  int  $menuOrder  Menu order for hierarchical post types
     * @param  string  $type  Post type (post, page, etc.)
     * @param  string  $mimeType  MIME type for attachments
     * @param  int  $commentCount  Number of comments
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly int $authorId = 0,
        public readonly string $date = '0000-00-00 00:00:00',
        public readonly string $dateGmt = '0000-00-00 00:00:00',
        public readonly string $content = '',
        public readonly string $title = '',
        public readonly string $excerpt = '',
        public readonly string $status = 'publish',
        public readonly string $commentStatus = 'open',
        public readonly string $pingStatus = 'open',
        public readonly string $password = '',
        public readonly string $slug = '',
        public readonly string $toPing = '',
        public readonly string $pinged = '',
        public readonly string $modified = '0000-00-00 00:00:00',
        public readonly string $modifiedGmt = '0000-00-00 00:00:00',
        public readonly string $contentFiltered = '',
        public readonly int $parentId = 0,
        public readonly string $guid = '',
        public readonly int $menuOrder = 0,
        public readonly string $type = 'post',
        public readonly string $mimeType = '',
        public readonly int $commentCount = 0,
    ) {}
}
