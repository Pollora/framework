<?php

declare(strict_types=1);

use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;
use Pollora\PostType\Infrastructure\Factories\PostTypeFactory;
use Pollora\Support\Facades\Constant;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyFactoryInterface;
use Pollora\Taxonomy\Infrastructure\Factories\TaxonomyFactory;

/**
 * Lists the facades of the framework with the class or interface they resolve.
 *
 * A facade that resolves a container alias (a string that is not a class) is left out:
 * there is no type to compare its annotations with.
 *
 * @return array<string, array{0: class-string, 1: class-string}>
 */
function facadesWithResolvedType(): array
{
    $facades = [];

    foreach (glob(dirname(__DIR__, 3).'/src/Support/Facades/*.php') ?: [] as $file) {
        $class = 'Pollora\\Support\\Facades\\'.basename($file, '.php');
        $accessor = (new ReflectionMethod($class, 'getFacadeAccessor'))->invoke(null);

        if (is_string($accessor) && (class_exists($accessor) || interface_exists($accessor))) {
            $facades[basename($file, '.php')] = [$class, $accessor];
        }
    }

    return $facades;
}

/**
 * Reads the `@method` tags of a class and returns the method names they declare.
 *
 * @return array<int, string>
 */
function annotatedMethods(string $class): array
{
    $doc = (new ReflectionClass($class))->getDocComment() ?: '';

    preg_match_all('/@method\s+static\s+\S+\s+(\w+)\(/', $doc, $matches);

    return $matches[1];
}

it('reads the facades and their resolved type', function (): void {
    $facades = facadesWithResolvedType();

    expect($facades)->toHaveKeys(['PostType', 'Taxonomy', 'WordPressError'])
        ->and(annotatedMethods(Constant::class))->toBeArray();
});

it('only annotates methods that the type resolved by the facade has', function (string $facade, string $resolved): void {
    foreach (annotatedMethods($facade) as $method) {
        expect(method_exists($resolved, $method))
            ->toBeTrue("{$facade} annotates {$method}() but {$resolved} has no such method");

        expect((new ReflectionMethod($resolved, $method))->isPublic())->toBeTrue();
    }
})->with(fn (): array => facadesWithResolvedType());

it('annotates every public method of a contract that a facade resolves', function (string $facade, string $resolved): void {
    $methods = array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        (new ReflectionClass($resolved))->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    expect(annotatedMethods($facade))->toEqualCanonicalizing($methods);
})->with(fn (): array => array_filter(
    facadesWithResolvedType(),
    static fn (array $pair): bool => interface_exists($pair[1]),
));

it('annotates the parameters of each method like the resolved type declares them', function (string $facade, string $resolved): void {
    $doc = (new ReflectionClass($facade))->getDocComment() ?: '';
    preg_match_all('/@method\s+static\s+\S+\s+(\w+)\(([^)]*)\)/', $doc, $matches, PREG_SET_ORDER);

    // The hook contracts of pollora/hook declare exists($hook) while the service behind them
    // (AbstractHook) also takes the callback and the priority: the facade documents the service.
    $widenedByTheService = ['Action::exists', 'Filter::exists'];

    foreach ($matches as [, $method, $parameters]) {
        if (in_array(class_basename($facade).'::'.$method, $widenedByTheService, true)) {
            continue;
        }

        $annotated = preg_match_all('/\$(\w+)/', $parameters, $names) ? $names[1] : [];
        $declared = array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName(),
            (new ReflectionMethod($resolved, $method))->getParameters(),
        );

        expect($annotated)->toBe($declared, "{$facade}::{$method}() parameters differ from {$resolved}");
    }
})->with(fn (): array => facadesWithResolvedType());

it('gives the factory contracts the same default priority as their implementations', function (string $contract, string $implementation): void {
    $default = static fn (string $class): mixed => (new ReflectionMethod($class, 'make'))
        ->getParameters()[array_key_last((new ReflectionMethod($class, 'make'))->getParameters())]
        ->getDefaultValue();

    expect((new ReflectionMethod($contract, 'make'))->getParameters())->not->toBeEmpty()
        ->and($default($contract))->toBe($default($implementation))
        ->and($default($contract))->toBe(5);
})->with([
    'post types' => [PostTypeFactoryInterface::class, PostTypeFactory::class],
    'taxonomies' => [TaxonomyFactoryInterface::class, TaxonomyFactory::class],
]);

it('names the priority parameter last in the factory contracts', function (string $contract): void {
    $parameters = (new ReflectionMethod($contract, 'make'))->getParameters();

    expect(end($parameters)->getName())->toBe('priority');
})->with([PostTypeFactoryInterface::class, TaxonomyFactoryInterface::class]);
