<?php

declare(strict_types=1);

namespace Tests\Unit\BlockBinding\Fixtures;

enum ConcertStatus: string
{
    case Announced = 'announced';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Announced => 'Coming soon',
            self::Cancelled => 'Cancelled',
        };
    }
}
