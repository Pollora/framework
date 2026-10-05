<?php

declare(strict_types=1);

namespace Tests\Unit\Meta\Fixtures;

use Pollora\Attributes\Meta;
use Pollora\Attributes\PostType;

#[PostType('event')]
class EventExtras
{
    #[Meta]
    public int $capacity = 0;
}
