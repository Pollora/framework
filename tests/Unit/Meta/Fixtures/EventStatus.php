<?php

declare(strict_types=1);

namespace Tests\Unit\Meta\Fixtures;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
