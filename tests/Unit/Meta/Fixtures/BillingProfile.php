<?php

declare(strict_types=1);

namespace Tests\Unit\Meta\Fixtures;

use Pollora\Attributes\Meta;
use Pollora\Attributes\UserMeta;

#[UserMeta]
class BillingProfile
{
    #[Meta]
    public ?string $jobTitle = null;
}
