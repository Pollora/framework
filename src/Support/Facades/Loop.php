<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for WordPress Loop functionality.
 *
 * Provides a clean interface to WordPress loop functions with proper type hints
 * and modern PHP syntax.
 *
 * @method static int id() Get current post ID
 * @method static string title($post = null) Get post title
 * @method static string author() Get post author name
 * @method static string authorMeta(string $field = '', int $user_id = 0) Get author meta data
 * @method static string content($more_text = null, $strip_teaser = false) Get post content
 * @method static string excerpt($post = null) Get post excerpt
 * @method static string thumbnail($size = 'post-thumbnail', $attr = '', $post = null) Get post thumbnail
 * @method static string|null thumbnailUrl($size = null, bool $icon = false) Get thumbnail URL
 * @method static string link($post = 0, bool $leavename = false) Get post permalink
 * @method static array category(int $id = 0) Get post categories
 * @method static array tags(int $id = 0) Get post tags
 * @method static array|false|\WP_Error terms(string $taxonomy, $post = 0) Get post terms
 * @method static string date(string $d = '', $post = null) Get post date
 * @method static string postClass($class = '', $post_id = null) Get post classes
 * @method static string nextPage(?string $label = null, int $max_page = 0) Get next page link
 * @method static string previousPage(?string $label = null) Get previous page link
 * @method static string|array paginate(array $args = []) Get pagination links
 *
 * @see \Pollora\View\Loop
 */
class Loop extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.loop';
    }
}
