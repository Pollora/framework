<?php

declare(strict_types=1);

/**
 * Remove everything seed.php creates, whether or not it all exists: it also
 * runs before seeding, to clear what an interrupted run left behind. Run with
 * e2e-full active, so the e2e_genre terms can still be deleted.
 */
$posts = get_posts([
    'post_type' => 'any',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'post_name__in' => [
        'e2e-hierarchy-front', 'e2e-hierarchy-blog', 'e2e-hierarchy-about', 'e2e-hierarchy-by-id',
        'e2e-hierarchy-plain', 'e2e-hierarchy-landing', 'e2e-hierarchy-routed', 'e2e-hierarchy-laravel',
        'e2e-hierarchy-php', 'e2e-hierarchy-post', 'e2e-hierarchy-book', 'e2e-hierarchy-note',
    ],
    'fields' => 'ids',
]);

foreach ($posts as $id) {
    wp_delete_post($id, true);
}

foreach ([['category', 'e2e-hierarchy-cat'], ['post_tag', 'e2e-hierarchy-tag'], ['e2e_genre', 'e2e-rock'], ['e2e_genre', 'e2e-jazz']] as [$taxonomy, $slug]) {
    $term = get_term_by('slug', $slug, $taxonomy);

    if ($term) {
        wp_delete_term($term->term_id, $taxonomy);
    }
}

$author = get_user_by('login', 'e2e-hierarchy-author');

if ($author) {
    require_once ABSPATH.'wp-admin/includes/user.php';
    wp_delete_user($author->ID);
}
