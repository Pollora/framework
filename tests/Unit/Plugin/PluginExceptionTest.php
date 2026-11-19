<?php

declare(strict_types=1);

use Pollora\Plugin\Domain\Exceptions\PluginException;

it('names the plugin and the reason in every failure message', function (string $factory, string $expected): void {
    $exception = PluginException::$factory('shop', 'because');

    expect($exception->getMessage())->toBe($expected);
})->with([
    'activation' => ['activationFailed', "Plugin 'shop' activation failed: because"],
    'deactivation' => ['deactivationFailed', "Plugin 'shop' deactivation failed: because"],
    'configuration' => ['configurationError', "Plugin 'shop' configuration error: because"],
    'dependency' => ['dependencyConflict', "Plugin 'shop' dependency conflict: because"],
    'file system' => ['fileSystemError', "Plugin 'shop' file system error: because"],
    'structure' => ['invalidStructure', "Plugin 'shop' has invalid structure: because"],
]);

it('keeps the code and the previous exception', function (): void {
    $previous = new RuntimeException('root cause');

    $exception = PluginException::activationFailed('shop', 'because', 42, $previous);

    expect($exception->getCode())->toBe(42)
        ->and($exception->getPrevious())->toBe($previous);
});

it('says which plugin was not found', function (): void {
    expect(PluginException::pluginNotFound('shop')->getMessage())->toBe("Plugin 'shop' not found");
});

it('points to the list command when a plugin name is unknown', function (): void {
    expect(PluginException::notFound('shop')->getMessage())
        ->toContain("Plugin 'shop' not found")
        ->toContain('pollora:plugin:list');
});
