<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use RectorLaravel\Rector\ArrayDimFetch\ServerVariableToRequestFacadeRector;
use RectorLaravel\Rector\MethodCall\ContainerBindConcreteWithClosureOnlyRector;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        AddOverrideAttributeToOverriddenMethodsRector::class,
        ContainerBindConcreteWithClosureOnlyRector::class => [
            __DIR__.'/src/Hook/Infrastructure/Providers/HookServiceProvider.php',
        ],
        // laravelIsServingTheRequest() exists to answer "is Laravel serving
        // this at all?" — it runs when PHP is executing wp-login.php or
        // wp-admin, where the application may not be bound. The facade needs
        // the very container whose absence the method is detecting, so reading
        // $_SERVER directly is the point rather than an oversight.
        ServerVariableToRequestFacadeRector::class => [
            __DIR__.'/src/WordPress/QueryTrait.php',
            // Its test sets $_SERVER['SCRIPT_FILENAME'] to drive that method;
            // going through the facade would stop it reaching the code at all.
            __DIR__.'/tests/Unit/WordPress/LaravelServingRequestTest.php',
        ],
        __DIR__.'/tests/Unit/helpers.php',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        codingStyle: true,
    )
    ->withPhpSets()
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_130,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
        LaravelSetList::LARAVEL_TYPE_DECLARATIONS,
    ]);
