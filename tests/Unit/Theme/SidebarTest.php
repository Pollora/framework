<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Mockery as m;
use Pollora\Theme\Domain\Models\Sidebar;

require_once __DIR__.'/../helpers.php';

describe('Sidebar', function () {
    it('resolves Application from Laravel container', function () {
        $mockApp = m::mock(Application::class);
        $mockAction = m::mock('Pollora\\Hook\\Infrastructure\\Services\\Action');
        // Simuler la résolution via le container Laravel
        $sidebar = new Sidebar($mockApp, $mockAction); // Adapter le constructeur si besoin
        $ref = new ReflectionProperty($sidebar, 'app');
        $ref->setAccessible(true);
        expect($ref->getValue($sidebar))->toBe($mockApp);
    });
});
