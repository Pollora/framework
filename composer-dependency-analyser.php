<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/*
 * ARCH-07: the harness preset (WordPress classes and functions, the tests), plus what is specific to the framework.
 */
$configuration = require __DIR__.'/vendor/pollora/harness/presets/composer-dependency-analyser.php';

return $configuration
    // Scan what is checked in: workbench/ (Testbench's skeleton) only exists once Testbench has built it.
    ->disableComposerAutoloadPathScan()
    ->addPathToScan(__DIR__.'/src', false)
    ->addPathToScan(__DIR__.'/tests', true)

    // Plugins and facades of WordPress plugins the core still names (ARCH-05: extracted into packages of their own),
    // and the global aliases a WordPress project defines.
    ->ignoreUnknownClasses([
        'DB',
        'GFAPI',
        'WP',
        'WC_Order',
        'Pollora\ThirdParty\WooCommerce\View\WooCommerceTemplateResolver',
        'Pollora\ThirdParty\WooCommerce\WooCommerce',
    ])

    // The framework requires the illuminate/* components it uses; when laravel/framework is installed (Testbench, a Pollora project)
    // it replaces them, and the analyser then attributes their classes to laravel/framework.
    ->ignoreErrorsOnPackage('laravel/framework', [ErrorType::SHADOW_DEPENDENCY])
    ->ignoreErrorsOnPackages([
        'illuminate/auth',
        'illuminate/config',
        'illuminate/console',
        'illuminate/database',
        'illuminate/routing',
        'illuminate/support',
    ], [ErrorType::UNUSED_DEPENDENCY])

    // Libraries every Laravel application brings with illuminate/*: named by the code, never required on their own.
    ->ignoreErrorsOnPackages([
        'psr/container',
        'psr/log',
        'symfony/console',
        'symfony/finder',
        'symfony/http-foundation',
        'symfony/http-kernel',
        'vlucas/phpdotenv',
    ], [ErrorType::SHADOW_DEPENDENCY])

    // The media module's storage comes with pollora/media (league/flysystem and its local adapter).
    ->ignoreErrorsOnPackages(['league/flysystem', 'league/flysystem-local'], [ErrorType::SHADOW_DEPENDENCY])

    // PHP extensions every WordPress host loads (PDO and MySQL for the database, fileinfo and zip for the modules).
    ->ignoreErrorsOnExtensions(['ext-fileinfo', 'ext-pdo', 'ext-zip'], [ErrorType::SHADOW_DEPENDENCY])

    // Loaded by Composer or at boot, not through a class the code names: patches applied on install,
    // the helper that must load before Laravel's, and the Bedrock autoloader that loads mu-plugins.
    ->ignoreErrorsOnPackages([
        'cweagans/composer-patches',
        'pollora/helper-overrider',
        'roots/bedrock-autoloader',
    ], [ErrorType::UNUSED_DEPENDENCY]);
