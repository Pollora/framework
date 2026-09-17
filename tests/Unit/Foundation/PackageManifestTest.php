<?php

declare(strict_types=1);

/**
 * Guards what Laravel's package discovery reads out of `composer.json`.
 *
 * Nothing verifies these entries at install time. `AliasLoader` registers an
 * alias without looking at its target and only calls `class_alias()` the first
 * time the short name is actually used — so a facade deleted without its alias
 * being removed installs cleanly, boots cleanly, and then throws
 * `Class "…" not found` on whichever page happens to use it. The `Loop` alias
 * survived that way for one release, `Query` for over two years.
 *
 * A provider is louder — it fails at boot rather than on a single page — but it
 * is the same class of mistake, so it is checked here too.
 */
$manifest = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/composer.json'), true);

/** @var array<string, string> $aliases */
$aliases = $manifest['extra']['laravel']['aliases'] ?? [];

/** @var list<string> $providers */
$providers = $manifest['extra']['laravel']['providers'] ?? [];

describe('package manifest', function () use ($aliases, $providers): void {
    it('declares at least one alias and one provider, so the checks below mean something', function () use ($aliases, $providers): void {
        expect($aliases)->not->toBeEmpty()
            ->and($providers)->not->toBeEmpty();
    });

    it('points every alias at a class the autoloader can resolve', function (string $short, string $fqcn): void {
        expect(class_exists($fqcn))->toBeTrue(
            sprintf('Alias "%s" points at %s, which does not exist.', $short, $fqcn)
        );
    })->with(array_map(
        static fn (string $short, string $fqcn): array => [$short, $fqcn],
        array_keys($aliases),
        array_values($aliases),
    ));

    it('points every provider at a class the autoloader can resolve', function (string $fqcn): void {
        expect(class_exists($fqcn))->toBeTrue(
            sprintf('Provider %s does not exist.', $fqcn)
        );
    })->with(array_map(static fn (string $fqcn): array => [$fqcn], $providers));

    it('registers no alias for a facade that has been removed', function () use ($aliases): void {
        // Named explicitly rather than left to the resolvable check above: these
        // two are the reason this file exists, and reintroducing either should
        // fail with a message that says so.
        expect($aliases)->not->toHaveKey('Loop')
            ->and($aliases)->not->toHaveKey('Query');
    });
});
