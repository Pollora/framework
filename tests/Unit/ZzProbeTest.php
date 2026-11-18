<?php

declare(strict_types=1);

use Pollora\Zzprobe\Probe;

it('adds two integers', function (): void {
    expect((new Probe)->total(2, 3))->toBe(5);
});

it('tells whether a number is above one hundred', function (): void {
    expect((new Probe)->isBig(500))->toBeTrue()
        ->and((new Probe)->isBig(100))->toBeFalse()
        ->and((new Probe)->isBig(101))->toBeTrue();
});
