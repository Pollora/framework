<?php

declare(strict_types=1);

// Skip these tests as they need a full Laravel application to run properly
test('feature tests are skipped until application testing setup is complete', function () {
    $this->markTestSkipped('Feature tests require a Laravel application');
});
