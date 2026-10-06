<?php

declare(strict_types=1);

namespace Tests\Unit\BlockBinding\Fixtures;

final class SeatCounter
{
    public function left(int $postId): int
    {
        return $postId * 2;
    }
}
