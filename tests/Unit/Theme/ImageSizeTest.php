<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Theme\Domain\Models\ImageSize;

require_once __DIR__.'/../helpers.php';

describe('ImageSize', function () {
    it('resolves Action from Laravel container', function () {
        $mockAction = m::mock('Pollora\\Hook\\Infrastructure\\Services\\Action');
        $imageSize = new ImageSize($mockAction);
        expect($imageSize)->toBeInstanceOf(ImageSize::class);
    });
});
