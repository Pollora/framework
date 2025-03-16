<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Mockery as m;
use Pollora\Attributes\Attributable;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Attributes\WpRestRoute;
use Pollora\Attributes\WpRestRoute\Method;
use Pollora\Support\Facades\Action;

// Classe de test qui implémente l'interface Attributable
#[WpRestRoute('api/v1', '/test', 'TestPermission')]
class TestController implements Attributable
{
    public ?string $namespace = null;

    public ?string $route = null;

    public ?string $classPermission = null;

    #[Method(['GET', 'POST'])]
    public function testMethod(string $param1): string
    {
        return "Test {$param1}";
    }

    #[Method('GET', 'CustomPermission')]
    public function testMethodWithCustomPermission(): string
    {
        return 'Test with custom permission';
    }
}

beforeAll(function () {
    // Créer et configurer le container
    $app = new Container;
    Facade::setFacadeApplication($app);

    // Définir un mock pour la façade Action
    $mock = m::mock('stdClass');
    $mock->shouldReceive('add')
        ->with('rest_api_init', m::type('Closure'))
        ->andReturnNull();

    // Enregistrer le mock dans le container avec la clé correcte
    $app->instance(\Pollora\Hook\Action::class, $mock);

    // S'assurer que la façade est réinitialisée
    Action::clearResolvedInstances();
    Action::setFacadeApplication($app);
});

afterAll(function () {
    m::close();
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

test('WpRestRoute attribute sets correct properties on class', function () {
    $controller = new TestController;
    AttributeProcessor::process($controller);

    expect($controller->namespace)->toBe('api/v1')
        ->and($controller->route)->toBe('/test')
        ->and($controller->classPermission)->toBe('TestPermission');
});

test('Method attribute validates HTTP methods correctly', function () {
    expect(fn () => new Method(['INVALID']))->toThrow(InvalidArgumentException::class);

    expect(fn () => new Method(['GET', 'POST']))->not->toThrow(InvalidArgumentException::class);
});

test('Method attribute correctly handles multiple HTTP methods', function () {
    $method = new Method(['GET', 'POST']);

    expect($method->getMethods())
        ->toBe(['GET', 'POST'])
        ->toBeArray()
        ->toHaveCount(2);
});

test('Method attribute accepts single HTTP method as string', function () {
    $method = new Method('GET');

    expect($method->getMethods())
        ->toBe(['GET'])
        ->toBeArray()
        ->toHaveCount(1);
});

test('Method attribute handles permission callbacks correctly', function () {
    $methodWithDefaultPermission = new Method('GET');
    $methodWithCustomPermission = new Method('GET', 'CustomPermission');

    expect($methodWithDefaultPermission->permissionCallback)->toBeNull()
        ->and($methodWithCustomPermission->permissionCallback)->toBe('CustomPermission');
});
