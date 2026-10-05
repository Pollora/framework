<?php

declare(strict_types=1);

namespace Tests\Unit\Meta\Fixtures;

use Pollora\Attributes\Meta;
use Pollora\Attributes\PostMeta;

#[PostMeta('page')]
class PageExtras
{
    #[Meta]
    public ?string $subtitle = null;
}
