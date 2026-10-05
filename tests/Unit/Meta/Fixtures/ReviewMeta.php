<?php

declare(strict_types=1);

namespace Tests\Unit\Meta\Fixtures;

use Pollora\Attributes\CommentMeta;
use Pollora\Attributes\Meta;

#[CommentMeta]
class ReviewMeta
{
    #[Meta]
    public int $rating = 5;
}
