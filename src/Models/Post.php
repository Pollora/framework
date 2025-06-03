<?php

declare(strict_types=1);

namespace Pollora\Models;

/**
 * Class Post
 *
 * @property int $ID
 * @property int $post_author
 * @property \DateTime $post_date
 * @property \DateTime $post_date_gmt
 * @property string $post_content
 * @property string $post_title
 * @property string $post_excerpt
 * @property string $post_status
 * @property string $comment_status
 * @property string $ping_status
 * @property string $post_password
 * @property string $post_name
 * @property string $to_ping
 * @property string $pinged
 * @property \DateTime $post_modified
 * @property \DateTime $post_modified_gmt
 * @property string $post_content_filtered
 * @property int $post_parent
 * @property string $guid
 * @property int $menu_order
 * @property string $post_type
 * @property string $post_mime_type
 * @property int $comment_count
 * @property-read string $permalink
 * @property-read \Illuminate\Database\Eloquent\Collection $meta
 * @property-read \Illuminate\Database\Eloquent\Collection $taxonomies
 * @property-read \Illuminate\Database\Eloquent\Collection $comments
 * @property-read \Pollora\Models\User $author
 *
 * @method static \Illuminate\Database\Eloquent\Builder published()
 * @method static \Illuminate\Database\Eloquent\Builder status($status)
 * @method static \Illuminate\Database\Eloquent\Builder type($type)
 * @method static \Illuminate\Database\Eloquent\Builder taxonomy($taxonomy, $term)
 */
class Post extends \Pollora\Colt\Model\Post
{
    /**
     * Convert the Post instance to a WP_Post object.
     */
    public function toWpPost(): \WP_Post
    {
        return new \WP_Post((object) $this->toArray());
    }
}
