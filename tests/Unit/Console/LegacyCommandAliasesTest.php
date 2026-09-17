<?php

declare(strict_types=1);

use Illuminate\Console\Attributes\Aliases;
use Illuminate\Console\Attributes\Signature;
use Pollora\Block\UI\Console\MakeBlockCommand;
use Pollora\Foundation\Console\Commands\MakeModelCommand;
use Pollora\Hook\UI\Console\ActionMakeCommand;
use Pollora\Hook\UI\Console\FilterMakeCommand;
use Pollora\Plugin\UI\Console\MakePluginCommand;
use Pollora\PostType\UI\Console\PostTypeMakeCommand;
use Pollora\Taxonomy\UI\Console\TaxonomyMakeCommand;
use Pollora\Theme\UI\Console\MakeThemeCommand;
use Pollora\Theme\UI\Console\RemoveThemeCommand;
use Pollora\WordPress\Commands\LaunchPadSetupCommand;
use Pollora\WpCli\UI\Console\WpCliMakeCommand;

/**
 * Commands were renamed to the Laravel colon convention; the old names must keep
 * resolving, e.g. `pollora:env-setup` in a project's composer.json scripts.
 *
 * @return array<int, string>
 */
function legacyCommandAliases(string $class): array
{
    $reflection = new ReflectionClass($class);

    $signature = $reflection->getAttributes(Signature::class)[0] ?? null;

    if ($signature !== null) {
        return $signature->newInstance()->aliases ?? [];
    }

    $aliases = $reflection->getAttributes(Aliases::class)[0] ?? null;

    return $aliases?->newInstance()->aliases ?? [];
}

it('keeps the pre-rename name as an alias', function (string $class, string $legacyName): void {
    expect(legacyCommandAliases($class))->toContain($legacyName);
})->with([
    [MakeBlockCommand::class, 'pollora:make-block'],
    [MakeModelCommand::class, 'pollora:make-model'],
    [ActionMakeCommand::class, 'pollora:make-action'],
    [FilterMakeCommand::class, 'pollora:make-filter'],
    [MakePluginCommand::class, 'pollora:make-plugin'],
    [PostTypeMakeCommand::class, 'pollora:make-posttype'],
    [TaxonomyMakeCommand::class, 'pollora:make-taxonomy'],
    [MakeThemeCommand::class, 'pollora:make-theme'],
    [RemoveThemeCommand::class, 'pollora:delete-theme'],
    [LaunchPadSetupCommand::class, 'pollora:env-setup'],
    [WpCliMakeCommand::class, 'pollora:make-wp-cli'],
]);
