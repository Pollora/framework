<?php

declare(strict_types=1);

/**
 * The extraction of Meta, Block Bindings and Roles into packages keeps every class name and every public
 * signature. This test freezes them: each type under src/Meta, src/Role and src/BlockBinding, with its kind,
 * parents, constants and public/protected methods (the fluent ones included: `MetaRecord::set()`, `fill()`
 * and `save()` return `static`).
 *
 * The snapshot is the contract. A class that moves to a package keeps its name and still resolves through
 * the autoloader, so the snapshot has to stay the same. Regenerate it only for a deliberate API change:
 *
 *     UPDATE_SURFACE=1 vendor/bin/pest tests/Feature/Extraction/PublicSurfaceTest.php
 */
const SURFACE_MODULES = ['Meta', 'Role', 'BlockBinding'];

/**
 * @return array<string, array<string, mixed>>
 */
function describeSurface(string $class): array
{
    $reflection = new ReflectionClass($class);

    $kind = match (true) {
        $reflection->isEnum() => 'enum',
        $reflection->isInterface() => 'interface',
        $reflection->isTrait() => 'trait',
        default => ($reflection->isAbstract() ? 'abstract ' : '').($reflection->isFinal() ? 'final ' : '').($reflection->isReadOnly() ? 'readonly ' : '').'class',
    };

    $type = static fn (?ReflectionType $t): string => $t instanceof ReflectionType ? (string) $t : 'mixed';

    $methods = [];
    foreach ($reflection->getMethods() as $method) {
        if ($method->isPrivate() || $method->getDeclaringClass()->getName() !== $class) {
            continue;
        }

        $params = array_map(
            static fn (ReflectionParameter $p): string => trim(
                ($p->isPassedByReference() ? '&' : '').($p->isVariadic() ? '...' : '').$type($p->getType()).' $'.$p->getName().($p->isOptional() && ! $p->isVariadic() ? ' = ?' : ''),
            ),
            $method->getParameters(),
        );
        $methods[$method->getName()] = ($method->isPublic() ? 'public' : 'protected').($method->isStatic() ? ' static' : '').($method->isAbstract() ? ' abstract' : '')
            .' ('.implode(', ', $params).'): '.$type($method->getReturnType());
    }

    ksort($methods);

    $constants = [];
    foreach ($reflection->getReflectionConstants() as $constant) {
        if ($constant->isPublic() && $constant->getDeclaringClass()->getName() === $class) {
            $constants[$constant->getName()] = $constant->isEnumCase() ? 'case' : 'const';
        }
    }

    ksort($constants);

    $interfaces = array_values(array_diff($reflection->getInterfaceNames(), $reflection->getParentClass() ? $reflection->getParentClass()->getInterfaceNames() : []));
    sort($interfaces);

    return [
        'kind' => $kind,
        'extends' => $reflection->getParentClass() ? $reflection->getParentClass()->getName() : null,
        'implements' => $interfaces,
        'constants' => $constants,
        'methods' => $methods,
    ];
}

/**
 * @return list<class-string>
 */
function surfaceClassesOnDisk(): array
{
    $classes = [];
    foreach (SURFACE_MODULES as $module) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/src/'.$module, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/config/')) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(dirname(__DIR__, 3).'/src/'), -4);
            $classes[] = 'Pollora\\'.str_replace('/', '\\', $relative);
        }
    }

    sort($classes);

    return $classes;
}

it('keeps the name and the public signatures of every Meta, Role and BlockBinding class', function (): void {
    $snapshot = __DIR__.'/surface.json';

    if (getenv('UPDATE_SURFACE') === '1') {
        $surface = [];
        foreach (surfaceClassesOnDisk() as $class) {
            $surface[$class] = describeSurface($class);
        }

        file_put_contents($snapshot, json_encode($surface, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }

    $expected = json_decode((string) file_get_contents($snapshot), true, 512, JSON_THROW_ON_ERROR);
    expect($expected)->not->toBeEmpty();

    $actual = [];
    foreach (array_keys($expected) as $class) {
        expect(class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class))
            ->toBeTrue("{$class} no longer resolves");
        $actual[$class] = describeSurface($class);
    }

    expect($actual)->toEqual($expected);
});
