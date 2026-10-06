<?php

declare(strict_types=1);

namespace Tests\Unit\Meta\Fixtures;

use Pollora\Attributes\Meta;
use Pollora\Attributes\UserMeta;

#[UserMeta]
class MemberProfile
{
    #[Meta(showInRest: true)]
    public bool $newsletterOptIn = false;

    #[Meta]
    public ?string $jobTitle = null;
}
