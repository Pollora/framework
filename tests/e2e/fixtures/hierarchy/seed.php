<?php

/**
 * The content the template hierarchy spec resolves, run through `wp eval` with
 * e2e-full active (its post types and taxonomy must be registered).
 *
 * Slugs are fixed because the fixture templates are named after them
 * (page-e2e-hierarchy-about, category-e2e-hierarchy-cat, …). Prints the URL of
 * every case, and the ids the spec needs, as JSON.
 */
$page = static fn (string $slug, array $extra = []): int => wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => $slug,
    'post_name' => $slug,
    ...$extra,
], true);

$authorId = wp_insert_user([
    'user_login' => 'e2e-hierarchy-author',
    'user_nicename' => 'e2e-hierarchy-author',
    'user_email' => 'e2e-hierarchy-author@example.test',
    'user_pass' => wp_generate_password(24),
    'role' => 'author',
]);

$ids = [
    'front' => $page('e2e-hierarchy-front'),
    'blog' => $page('e2e-hierarchy-blog'),
    'about' => $page('e2e-hierarchy-about'),
    'byId' => $page('e2e-hierarchy-by-id'),
    'plain' => $page('e2e-hierarchy-plain'),
    'landing' => $page('e2e-hierarchy-landing', ['meta_input' => ['_wp_page_template' => 'templates/landing.blade.php']]),
    'routed' => $page('e2e-hierarchy-routed'),
    'laravel' => $page('e2e-hierarchy-laravel'),
    'php' => $page('e2e-hierarchy-php'),
];

$category = wp_insert_term('E2E hierarchy category', 'category', ['slug' => 'e2e-hierarchy-cat']);
$rock = wp_insert_term('E2E rock', 'e2e_genre', ['slug' => 'e2e-rock']);
$jazz = wp_insert_term('E2E jazz', 'e2e_genre', ['slug' => 'e2e-jazz']);

$ids['post'] = wp_insert_post([
    'post_type' => 'post',
    'post_status' => 'publish',
    'post_title' => 'e2e-hierarchy-post',
    'post_name' => 'e2e-hierarchy-post',
    'post_author' => $authorId,
    'post_date' => '2020-01-15 10:00:00',
    'post_category' => [$category['term_id']],
    'tags_input' => ['e2e-hierarchy-tag'],
], true);

$ids['book'] = wp_insert_post(['post_type' => 'e2e_book', 'post_status' => 'publish', 'post_title' => 'e2e-hierarchy-book', 'post_name' => 'e2e-hierarchy-book'], true);
wp_set_object_terms($ids['book'], [$rock['term_id'], $jazz['term_id']], 'e2e_genre');
$ids['note'] = wp_insert_post(['post_type' => 'e2e_note', 'post_status' => 'publish', 'post_title' => 'e2e-hierarchy-note', 'post_name' => 'e2e-hierarchy-note'], true);

foreach ([$authorId, $category, $rock, $jazz, ...array_values($ids)] as $created) {
    if (is_wp_error($created)) {
        fwrite(STDERR, $created->get_error_message()."\n");
        exit(1);
    }
}

// A static front page and a posts page, so front-page and home are both reachable.
update_option('show_on_front', 'page');
update_option('page_on_front', $ids['front']);
update_option('page_for_posts', $ids['blog']);

echo json_encode([
    'ids' => $ids,
    'urls' => [
        'front' => home_url('/'),
        'blog' => get_permalink($ids['blog']),
        'about' => get_permalink($ids['about']),
        'byId' => get_permalink($ids['byId']),
        'plain' => get_permalink($ids['plain']),
        'landing' => get_permalink($ids['landing']),
        'routed' => get_permalink($ids['routed']),
        'laravel' => get_permalink($ids['laravel']),
        'php' => get_permalink($ids['php']),
        'post' => get_permalink($ids['post']),
        'book' => get_permalink($ids['book']),
        'bookArchive' => get_post_type_archive_link('e2e_book'),
        'note' => get_permalink($ids['note']),
        'noteArchive' => get_post_type_archive_link('e2e_note'),
        'rock' => get_term_link('e2e-rock', 'e2e_genre'),
        'jazz' => get_term_link('e2e-jazz', 'e2e_genre'),
        'category' => get_category_link($category['term_id']),
        'tag' => get_term_link('e2e-hierarchy-tag', 'post_tag'),
        'author' => get_author_posts_url($authorId),
        'date' => get_month_link(2020, 1),
        'search' => home_url('/?s=e2e-hierarchy'),
        'notFound' => home_url('/e2e-hierarchy-nothing-here'),
    ],
]);
