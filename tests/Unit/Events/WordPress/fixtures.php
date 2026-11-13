<?php

declare(strict_types=1);

namespace Tests\Unit\Events\Fixtures;

/*
 * Minimal WordPress object doubles shared by the event tests. The WordPress
 * classes are only declared when no other test did it first, and the doubles
 * extend them so the tests never depend on who declared them.
 */

if (! class_exists('WP_Post', false)) {
    eval('class WP_Post { public int $ID = 1; public function __construct($post = null) {} }');
}

if (! class_exists('WP_User', false)) {
    eval('class WP_User { public int $ID = 0; }');
}

if (! class_exists('WP_Comment', false)) {
    eval('class WP_Comment {}');
}

if (! class_exists('WP_Site', false)) {
    eval('class WP_Site {}');
}

if (! class_exists('WP_Term', false)) {
    eval('class WP_Term { public $term_id = 0; public $taxonomy = ""; }');
}

if (! class_exists('WP_Error', false)) {
    eval('class WP_Error { public function __construct() {} }');
}

if (! class_exists('WP_Upgrader', false)) {
    eval('class WP_Upgrader {}');
}

class FakePost extends \WP_Post
{
    public string $post_type = 'post';

    public function __construct(public string $label = 'post', string $postType = 'post')
    {
        $this->post_type = $postType;
    }
}

class FakeComment extends \WP_Comment
{
    public function __construct(public string $comment_type = 'comment') {}
}

class FakeSite extends \WP_Site
{
    public function __construct(public int $blog_id = 1) {}
}

class FakeTerm extends \WP_Term
{
    public function __construct(public string $label = 'term') {}
}

class FakeUser extends \WP_User
{
    /**
     * @param  array<int, string>  $roles
     */
    public function __construct(int $id = 1, public array $roles = [], private readonly bool $exists = true)
    {
        $this->ID = $id;
    }

    public function exists(): bool
    {
        return $this->exists;
    }
}

class FakeError extends \WP_Error
{
    public function __construct(private readonly string $code, private readonly string $message) {}

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }
}

class FakeUpgrader extends \WP_Upgrader
{
    public object $skin;

    public array $result = [];

    /**
     * @param  array<string, mixed>  $skinProperties
     */
    public function __construct(array $skinProperties = [])
    {
        $this->skin = (object) $skinProperties;
    }
}

/**
 * A dispatcher under test together with the list of Laravel events it dispatched.
 *
 * @template T of object
 *
 * @param  class-string<T>  $class
 * @return array{0: T, 1: \ArrayObject<int, object>}
 */
function make_dispatcher(string $class): array
{
    $log = new \ArrayObject;

    $events = \Mockery::mock(\Illuminate\Contracts\Events\Dispatcher::class);
    $events->shouldReceive('dispatch')->andReturnUsing(function (object $event) use ($log): void {
        $log[] = $event;
    });

    return [new $class($events, \Mockery::mock(\Pollora\Hook\Domain\Contract\Action::class)), $log];
}
