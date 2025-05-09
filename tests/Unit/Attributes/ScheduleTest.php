<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Mockery as m;
use Pollora\Attributes\Attributable;
use Pollora\Attributes\Schedule;
use Pollora\Support\Facades\Action;

#[Schedule('hourly')]
class TestScheduledTask implements Attributable
{
    public function testMethod(): void
    {
        // Méthode de test
    }

    #[Schedule(['interval' => 3600, 'display' => 'Every hour'], 'custom_hook')]
    public function testCustomSchedule(): void
    {
        // Méthode de test avec planning personnalisé
    }
}

beforeEach(function () {
    // Initialisation des mocks WordPress sans setupWordPressMocks
    // Car on veut une configuration très spécifique
    WP::$wpFunctions = m::mock('stdClass');
    WP::$wpFunctions->shouldReceive('wp_next_scheduled')->andReturn(false);
    WP::$wpFunctions->shouldReceive('wp_schedule_event')->andReturn(true);
    WP::$wpFunctions->shouldReceive('add_filter')->withAnyArgs()->andReturn(true);

    // Configuration du container pour la façade
    $app = new Container;
    Facade::setFacadeApplication($app);

    // Mock de la façade Action
    $actionMock = m::mock('stdClass');
    $actionMock->shouldReceive('add')->withAnyArgs()->andReturnNull();

    $app->instance(Action::class, $actionMock);
    Action::clearResolvedInstances();
    Action::setFacadeApplication($app);
});

afterEach(function () {
    m::close();
    WP::$wpFunctions = null;
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

test('Schedule attribute validates predefined recurrence correctly', function () {
    // Test avec un planning valide
    expect(fn () => new Schedule('hourly'))->not->toThrow(InvalidArgumentException::class);

    // Test avec un planning invalide
    $expectedMessage = 'Invalid recurrence schedule "invalid_schedule". Valid schedules are: hourly, twicedaily, daily, weekly';
    expect(fn () => new Schedule('invalid_schedule'))
        ->toThrow(InvalidArgumentException::class, $expectedMessage);
});

test('Schedule attribute validates custom recurrence correctly', function () {
    // Test avec un planning personnalisé valide
    $validSchedule = [
        'interval' => 3600,
        'display' => 'Every hour',
    ];
    expect(fn () => new Schedule($validSchedule))->not->toThrow(InvalidArgumentException::class);

    // Test avec un planning personnalisé invalide (sans interval)
    $invalidSchedule1 = [
        'display' => 'Every hour',
    ];
    expect(fn () => new Schedule($invalidSchedule1))
        ->toThrow(
            InvalidArgumentException::class,
            'Custom recurrence must include a numeric interval in seconds'
        );

    // Test avec un planning personnalisé invalide (sans display)
    $invalidSchedule2 = [
        'interval' => 3600,
    ];
    expect(fn () => new Schedule($invalidSchedule2))
        ->toThrow(
            InvalidArgumentException::class,
            'Custom recurrence must include a display name'
        );
});

test('Schedule attribute generates correct hook name', function () {
    $reflection = new ReflectionClass(TestScheduledTask::class);
    $method = $reflection->getMethod('testMethod');

    $schedule = new Schedule('hourly');
    $instance = new TestScheduledTask;

    // Utilisation de la réflexion pour accéder à la méthode privée generateHookName
    $generateHookName = new ReflectionMethod(Schedule::class, 'generateHookName');
    $generateHookName->setAccessible(true);

    // Le changement est ici: n'envoyez que le paramètre $method
    $hookName = $generateHookName->invoke($schedule, $method);

    expect($hookName)->toBe('test_scheduled_task_test_method');
});

test('Schedule attribute handles custom hook names', function () {
    // Stocker la Closure pour l'exécuter plus tard
    $initCallback = null;

    // Créer un nouveau mock spécifique pour ce test
    $actionMock = m::mock('stdClass');
    $actionMock->shouldReceive('add')
        ->with('init', m::type('Closure'))
        ->once()
        ->andReturnUsing(function ($hook, $callback) use (&$initCallback) {
            $initCallback = $callback;
        });

    $actionMock->shouldReceive('add')
        ->with('custom_hook_name', m::type('array'))
        ->once()
        ->andReturnNull();

    // Remplacer le mock dans le container
    $app = Facade::getFacadeApplication();
    $app->instance(\Pollora\Hook\Action::class, $actionMock);

    $schedule = new Schedule('hourly', 'custom_hook_name');
    $reflection = new ReflectionClass(TestScheduledTask::class);
    $method = $reflection->getMethod('testMethod');
    $instance = new TestScheduledTask;

    $schedule->handle($instance, $method);

    // Exécuter la Closure stockée
    if ($initCallback) {
        $initCallback();
    }
});

test('Schedule attribute registers custom schedule', function () {
    // Stocker la Closure pour l'exécuter plus tard
    $initCallback = null;

    // Mock de Action pour capturer la Closure
    $actionMock = m::mock('stdClass');
    $actionMock->shouldReceive('add')
        ->with('init', m::type('Closure'))
        ->once()
        ->andReturnUsing(function ($hook, $callback) use (&$initCallback) {
            $initCallback = $callback;
        });

    $actionMock->shouldReceive('add')
        ->with('custom_schedule_hook', m::type('array'))
        ->once()
        ->andReturnNull();

    // Remplacer le mock dans le container
    $app = Facade::getFacadeApplication();
    $app->instance(\Pollora\Hook\Action::class, $actionMock);

    // Reset the WordPress mock for this specific test
    WP::$wpFunctions = m::mock('stdClass');

    // Vérifier que wp_next_scheduled est appelé
    WP::$wpFunctions
        ->shouldReceive('wp_next_scheduled')
        ->once()
        ->with('custom_schedule_hook', [])
        ->andReturn(false);

    // Vérifier que wp_schedule_event est appelé
    WP::$wpFunctions
        ->shouldReceive('wp_schedule_event')
        ->once()
        ->withArgs(function ($timestamp, $recurrence, $hook, $args) {
            return is_numeric($timestamp)
                && $hook === 'custom_schedule_hook'
                && $args === [];
        })
        ->andReturn(true);

    // Vérifier que add_filter est appelé pour le planning personnalisé
    WP::$wpFunctions
        ->shouldReceive('add_filter')
        ->withAnyArgs()
        ->once()
        ->andReturnUsing(function ($hook, $callback) {
            $schedules = [];
            $result = $callback($schedules);

            // Vérifier que le planning personnalisé a été ajouté
            expect($result)->toHaveKey('custom_schedule_hook')
                ->and($result['custom_schedule_hook'])->toHaveKey('interval', 3600)
                ->and($result['custom_schedule_hook'])->toHaveKey('display', 'Every hour');

            return $result;
        });

    $customSchedule = [
        'interval' => 3600,
        'display' => 'Every hour',
    ];

    $schedule = new Schedule($customSchedule, 'custom_schedule_hook');
    $reflection = new ReflectionClass(TestScheduledTask::class);
    $method = $reflection->getMethod('testCustomSchedule');
    $instance = new TestScheduledTask;

    $schedule->handle($instance, $method);

    // Exécuter la Closure stockée
    if ($initCallback) {
        $initCallback();
    }
});
