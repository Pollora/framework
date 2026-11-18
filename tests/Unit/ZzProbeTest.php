<?php

declare(strict_types=1);

use Pollora\Zzprobe\Probe;

it('runs the probe without asserting anything', function (): void {
    (new Probe)->total(2, 3);
    (new Probe)->isBig(500);

    expect(true)->toBeTrue();
});
