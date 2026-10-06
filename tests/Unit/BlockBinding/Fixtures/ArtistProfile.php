<?php

declare(strict_types=1);

namespace Tests\Unit\BlockBinding\Fixtures;

use Pollora\Attributes\Meta;
use Pollora\Attributes\UserMeta;

#[UserMeta]
class ArtistProfile
{
    #[Meta(public: true)]
    public ?string $stageName = null;

    #[Meta(showInRest: true)]
    public ?string $phone = null;
}
