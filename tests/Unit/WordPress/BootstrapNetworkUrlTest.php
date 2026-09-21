<?php

declare(strict_types=1);

use Pollora\WordPress\Bootstrap;

/**
 * Guards the multisite regression where `network_site_url` fatals.
 *
 * WordPress declares `network_site_url( $path = '', $scheme = null )`, so the
 * `network_site_url` filter is applied with a null `$scheme` on every request
 * that does not ask for an explicit scheme. A non-nullable `string $scheme`
 * parameter therefore throws a TypeError and takes down the whole admin as
 * soon as MULTISITE is enabled.
 *
 * The signature is asserted by reflection rather than by calling the method,
 * because a real invocation would require bootstrapping WordPress itself.
 */
it('accepts a null scheme on rewriteNetworkUrl, as WordPress passes it', function () {
    $parameter = (new ReflectionMethod(Bootstrap::class, 'rewriteNetworkUrl'))
        ->getParameters()[2];

    expect($parameter->getName())->toBe('scheme')
        ->and($parameter->allowsNull())->toBeTrue()
        ->and($parameter->isOptional())->toBeTrue();
});

it('keeps path optional on rewriteNetworkUrl, matching the filter signature', function () {
    $parameter = (new ReflectionMethod(Bootstrap::class, 'rewriteNetworkUrl'))
        ->getParameters()[1];

    expect($parameter->getName())->toBe('path')
        ->and($parameter->isOptional())->toBeTrue();
});
