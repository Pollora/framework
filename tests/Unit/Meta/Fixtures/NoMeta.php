<?php

declare(strict_types=1);

namespace Tests\Unit\Meta\Fixtures;

use Pollora\Attributes\PostType;

#[PostType('no-meta')]
class NoMeta
{
    public string $title = '';
}
