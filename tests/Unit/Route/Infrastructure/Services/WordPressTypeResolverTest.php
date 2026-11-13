<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Route\Infrastructure\Services\Resolvers\WordPressTypeResolver;

/**
 * Declare the WordPress classes the resolver type-hints, in the shape the
 * other route tests give them.
 */
function contentDeclareWordPressClasses(): void
{
    if (! class_exists('WP_Post')) {
        eval('namespace { class WP_Post { public int $ID = 1; public function __construct($post = null) {} } }');
    }

    if (! class_exists('WP_User')) {
        eval('class WP_User { public int $ID = 0; }');
    }

    if (! class_exists('WP_Query')) {
        eval('class WP_Query {}');
    }

    if (! class_exists('WP')) {
        eval('class WP {}');
    }
}

beforeEach(function (): void {
    contentDeclareWordPressClasses();

    $this->globals = [];
    foreach (['post', 'wp_query', 'wp'] as $name) {
        $this->globals[$name] = $GLOBALS[$name] ?? null;
        unset($GLOBALS[$name]);
    }

    $this->resolver = new WordPressTypeResolver;
});

afterEach(function (): void {
    foreach ($this->globals as $name => $value) {
        if ($value === null) {
            unset($GLOBALS[$name]);
        } else {
            $GLOBALS[$name] = $value;
        }
    }
});

describe('WordPressTypeResolver', function (): void {
    it('has nothing to offer for a type it does not know', function (): void {
        expect($this->resolver->resolve('WP_Comment'))->toBeNull()
            ->and($this->resolver->resolve('stdClass'))->toBeNull();
    });

    it('resolves the current post from the global first', function (): void {
        $global = new WP_Post;
        $GLOBALS['post'] = $global;
        Functions\when('get_queried_object')->justReturn(new WP_Post);

        expect($this->resolver->resolve('WP_Post'))->toBe($global);
    });

    it('falls back to the queried object when the global post is not a post', function (): void {
        $queried = new WP_Post;
        $GLOBALS['post'] = 'not a post';
        Functions\when('get_queried_object')->justReturn($queried);

        expect($this->resolver->resolvePost())->toBe($queried);
    });

    it('has no post when the queried object is something else', function (): void {
        Functions\when('get_queried_object')->justReturn(new WP_Term);

        expect($this->resolver->resolvePost())->toBeNull();
    });

    it('resolves the queried term and nothing else', function (): void {
        $term = new WP_Term;
        Functions\when('get_queried_object')->justReturn($term);

        expect($this->resolver->resolve('WP_Term'))->toBe($term);

        Functions\when('get_queried_object')->justReturn(new WP_Post);

        expect($this->resolver->resolveTerm())->toBeNull();
    });

    it('resolves the queried user before the logged in one', function (): void {
        $queried = new WP_User;
        $queried->ID = 7;
        $current = new WP_User;
        $current->ID = 3;
        Functions\when('get_queried_object')->justReturn($queried);
        Functions\when('wp_get_current_user')->justReturn($current);

        expect($this->resolver->resolve('WP_User'))->toBe($queried);
    });

    it('falls back to the logged in user when the queried object is not a user', function (): void {
        $current = new WP_User;
        $current->ID = 3;
        Functions\when('get_queried_object')->justReturn(new WP_Post);
        Functions\when('wp_get_current_user')->justReturn($current);

        expect($this->resolver->resolveUser())->toBe($current);
    });

    it('does not take a visitor for a user', function (): void {
        $visitor = new WP_User;
        $visitor->ID = 0;
        Functions\when('get_queried_object')->justReturn(null);
        Functions\when('wp_get_current_user')->justReturn($visitor);

        expect($this->resolver->resolveUser())->toBeNull();
    });

    it('resolves the global WP_Query and WP objects only when they are the right type', function (): void {
        expect($this->resolver->resolve('WP_Query'))->toBeNull()
            ->and($this->resolver->resolve('WP'))->toBeNull();

        $GLOBALS['wp_query'] = new WP_Query;
        $GLOBALS['wp'] = new WP;

        expect($this->resolver->resolve('WP_Query'))->toBe($GLOBALS['wp_query'])
            ->and($this->resolver->resolve('WP'))->toBe($GLOBALS['wp']);

        $GLOBALS['wp_query'] = new stdClass;
        $GLOBALS['wp'] = 'nope';

        expect($this->resolver->resolveQuery())->toBeNull()
            ->and($this->resolver->resolveWP())->toBeNull();
    });
});
