<?php

declare(strict_types=1);

namespace Tests\Unit\BlockBinding\Fixtures;

use Illuminate\Support\Sleep;
use Pollora\Attributes\BlockBinding;
use Pollora\Attributes\BlockBinding\BindingField;

#[BlockBinding('acme/slow', label: 'Slow')]
final class SlowBinding
{
    #[BindingField]
    public function late(): string
    {
        Sleep::usleep(60_000);

        return 'late';
    }

    #[BindingField]
    public function quick(): string
    {
        return 'quick';
    }
}
