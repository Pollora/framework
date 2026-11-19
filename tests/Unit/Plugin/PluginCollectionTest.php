<?php

declare(strict_types=1);

use Pollora\Plugin\Domain\Models\PluginModule;
use Pollora\Plugin\Domain\Support\PluginCollection;

function pluginCollectionOf(): PluginCollection
{
    $alpha = new PluginModule('alpha', '/plugins/alpha');
    $alpha->activate();
    $alpha->enable();

    $beta = new PluginModule('beta', '/plugins/beta');

    $gamma = new PluginModule('gamma', '/plugins/gamma');
    $gamma->activate();

    return new PluginCollection([$gamma, $beta, $alpha]);
}

it('keeps only the active plugins', function (): void {
    expect(pluginCollectionOf()->active()->map->getName()->values()->all())->toBe(['gamma', 'alpha']);
});

it('keeps only the inactive plugins', function (): void {
    expect(pluginCollectionOf()->inactive()->map->getName()->values()->all())->toBe(['beta']);
});

it('keeps only the enabled plugins and only the disabled ones', function (): void {
    $plugins = pluginCollectionOf();

    // PluginModule ties "enabled" to its active flag, so the two filters are exact complements.
    expect($plugins->enabled()->map->getName()->values()->all())->toBe(['gamma', 'alpha'])
        ->and($plugins->disabled()->map->getName()->values()->all())->toBe(['beta']);
});

it('sorts by name, ascending by default and descending on request', function (): void {
    $plugins = pluginCollectionOf();

    expect($plugins->sortByName()->map->getName()->values()->all())->toBe(['alpha', 'beta', 'gamma'])
        ->and($plugins->sortByName('desc')->map->getName()->values()->all())->toBe(['gamma', 'beta', 'alpha']);
});

it('finds a plugin by name or by slug, and returns null for an unknown one', function (): void {
    $plugins = pluginCollectionOf();

    expect($plugins->findByName('beta')?->getName())->toBe('beta')
        ->and($plugins->findBySlug('alpha')?->getName())->toBe('alpha')
        ->and($plugins->findByName('missing'))->toBeNull()
        ->and($plugins->findBySlug('missing'))->toBeNull();
});

it('looks a slug up in lower case', function (): void {
    $plugins = new PluginCollection([new PluginModule('MyShop', '/plugins/MyShop')]);

    expect($plugins->findBySlug('myshop')?->getName())->toBe('MyShop');
});
