<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Adapters;

use Pollora\Route\Domain\Models\NullablePostEntity;
use WP_Post;

/**
 * WordPress adapter for the NullablePostEntity domain model.
 *
 * This class provides conversion between domain NullablePostEntity objects
 * and WordPress WP_Post objects.
 */
class WordPressNullablePost
{
    /**
     * Convert a domain NullablePostEntity to a WordPress WP_Post.
     *
     * @param  NullablePostEntity  $entity  The domain entity to convert
     * @return WP_Post The WordPress post object
     */
    public function toWpPost(NullablePostEntity $entity): WP_Post
    {
        // Convert the entity to a simple object with WordPress-style property names
        $wpPost = new \stdClass;
        $wpPost->ID = $entity->id;
        $wpPost->post_author = $entity->authorId;
        $wpPost->post_date = $entity->date;
        $wpPost->post_date_gmt = $entity->dateGmt;
        $wpPost->content = $entity->content;
        $wpPost->post_title = $entity->title;
        $wpPost->post_excerpt = $entity->excerpt;
        $wpPost->post_status = $entity->status;
        $wpPost->comment_status = $entity->commentStatus;
        $wpPost->ping_status = $entity->pingStatus;
        $wpPost->post_password = $entity->password;
        $wpPost->post_name = $entity->slug;
        $wpPost->to_ping = $entity->toPing;
        $wpPost->pinged = $entity->pinged;
        $wpPost->post_modified = $entity->modified;
        $wpPost->post_modified_gmt = $entity->modifiedGmt;
        $wpPost->post_content_filtered = $entity->contentFiltered;
        $wpPost->post_parent = $entity->parentId;
        $wpPost->guid = $entity->guid;
        $wpPost->menu_order = $entity->menuOrder;
        $wpPost->post_type = $entity->type;
        $wpPost->post_mime_type = $entity->mimeType;
        $wpPost->comment_count = $entity->commentCount;

        // Convert to WP_Post
        return new WP_Post($wpPost);
    }

    /**
     * Convert a WordPress WP_Post to a domain NullablePostEntity.
     *
     * @param  WP_Post|null  $post  The WordPress post to convert
     * @return NullablePostEntity The domain entity
     */
    public function toDomainEntity(?WP_Post $post = null): NullablePostEntity
    {
        if ($post === null) {
            return new NullablePostEntity;
        }

        return new NullablePostEntity(
            id: $post->ID,
            authorId: (int) $post->post_author,
            date: $post->post_date,
            dateGmt: $post->post_date_gmt,
            content: $post->post_content ?? '',
            title: $post->post_title,
            excerpt: $post->post_excerpt,
            status: $post->post_status,
            commentStatus: $post->comment_status,
            pingStatus: $post->ping_status,
            password: $post->post_password,
            slug: $post->post_name,
            toPing: $post->to_ping,
            pinged: $post->pinged,
            modified: $post->post_modified,
            modifiedGmt: $post->post_modified_gmt,
            contentFiltered: $post->post_content_filtered,
            parentId: (int) $post->post_parent,
            guid: $post->guid,
            menuOrder: (int) $post->menu_order,
            type: $post->post_type,
            mimeType: $post->post_mime_type,
            commentCount: (int) $post->comment_count
        );
    }

    /**
     * Create a WordPress WP_Post with default values.
     *
     * @return WP_Post A default WordPress post
     */
    public function createDefaultWpPost(): WP_Post
    {
        return $this->toWpPost(new NullablePostEntity);
    }
}
