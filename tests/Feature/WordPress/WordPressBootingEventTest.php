<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\WordPress\Bootstrap;
use Pollora\WordPress\Events\WordPressBooting;

/**
 * WordPressBooting is the one moment a tool can act before WordPress builds its
 * globals. The settings file itself is skipped by running as WP-CLI, which
 * loads it on its own, so the test never needs a WordPress install.
 */
it('announces WordPress before its settings file loads', function (): void {
    Event::fake([WordPressBooting::class]);

    $console = Mockery::mock(ConsoleDetectionService::class);
    $console->shouldReceive('isConsole')->andReturn(false);
    $console->shouldReceive('isWpCli')->andReturn(true);

    $bootstrap = new Bootstrap(
        $console,
        Mockery::mock(DebugDetectorInterface::class),
        Mockery::mock(Action::class),
    );

    $database = new ReflectionProperty(Bootstrap::class, 'db');
    $database->setValue($bootstrap, ['prefix' => 'wp_']);

    (new ReflectionMethod(Bootstrap::class, 'loadWordPressSettings'))->invoke($bootstrap);

    Event::assertDispatched(
        WordPressBooting::class,
        fn (WordPressBooting $event): bool => $event->lightweight === false,
    );
});
