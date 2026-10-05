<?php

declare(strict_types=1);

namespace Tests\Unit\Meta\Fixtures;

use Pollora\Models\Post;

class EventPostModel extends Post
{
    protected $postType = 'fixture_event';
}
