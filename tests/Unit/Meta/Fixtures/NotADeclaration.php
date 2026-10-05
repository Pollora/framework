<?php

declare(strict_types=1);

namespace Tests\Unit\Meta\Fixtures;

use Pollora\Attributes\Meta;

class NotADeclaration
{
    #[Meta]
    public string $color = '';
}
