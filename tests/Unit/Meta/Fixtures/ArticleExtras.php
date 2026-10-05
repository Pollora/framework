<?php

declare(strict_types=1);

namespace Tests\Unit\Meta\Fixtures;

use Pollora\Attributes\Meta;
use Pollora\Attributes\PostMeta;

#[PostMeta(['post', 'page'])]
class ArticleExtras
{
    #[Meta(showInRest: true, revisions: true)]
    public ?string $subtitle = null;
}
