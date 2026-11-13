<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Mockery as m;
use Pollora\Theme\Domain\Exceptions\ThemeSetupException;
use Pollora\Theme\Infrastructure\Providers\ThemeComponentProvider;
use Psr\Log\LoggerInterface;

final class ExplodingThemeComponent
{
    public function register(): void
    {
        throw new RuntimeException('component exploded');
    }
}

final class WorkingThemeComponent
{
    public static int $registered = 0;

    public function register(): void
    {
        self::$registered++;
    }
}

function componentProvider(bool $debug, ?LoggerInterface $logger, array $components): ThemeComponentProvider
{
    $app = new Application(sys_get_temp_dir());
    $app->instance('config', new Repository(['app' => ['debug' => $debug]]));
    if ($logger instanceof LoggerInterface) {
        $app->instance(LoggerInterface::class, $logger);
    }
    Container::setInstance($app);

    $provider = new ThemeComponentProvider($app);
    (new ReflectionProperty($provider, 'components'))->setValue($provider, $components);

    return $provider;
}

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
    WorkingThemeComponent::$registered = 0;
});

afterEach(function (): void {
    Container::setInstance($this->previousContainer);
});

describe('ThemeComponentProvider failures', function (): void {
    it('rethrows a failing component as a setup exception in debug mode', function (): void {
        componentProvider(true, null, [ExplodingThemeComponent::class])->register();
    })->throws(ThemeSetupException::class, 'Failed to register component ExplodingThemeComponent: component exploded');

    it('keeps the original exception as the cause', function (): void {
        try {
            componentProvider(true, null, [ExplodingThemeComponent::class])->register();
        } catch (ThemeSetupException $e) {
            expect($e->getPrevious())->toBeInstanceOf(RuntimeException::class);

            return;
        }

        $this->fail('Expected a ThemeSetupException');
    });

    it('logs the failure and registers the remaining components outside debug mode', function (): void {
        $messages = [];
        $logger = m::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->andReturnUsing(function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        componentProvider(false, $logger, [ExplodingThemeComponent::class, WorkingThemeComponent::class])->register();

        expect($messages)->toBe(['Theme component registration failed: ExplodingThemeComponent - component exploded'])
            ->and(WorkingThemeComponent::$registered)->toBe(1);
    });

    it('carries on silently when the failure cannot even be logged', function (): void {
        componentProvider(false, null, [ExplodingThemeComponent::class, WorkingThemeComponent::class])->register();

        expect(WorkingThemeComponent::$registered)->toBe(1);
    });

    it('adds a component only once', function (): void {
        $provider = componentProvider(false, null, []);

        $provider->addComponent(WorkingThemeComponent::class)->addComponent(WorkingThemeComponent::class);
        $provider->register();

        expect(WorkingThemeComponent::$registered)->toBe(1);
    });
});
