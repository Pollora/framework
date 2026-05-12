<?php

declare(strict_types=1);

namespace Pollora\Foundation\Console;

use Illuminate\Console\Application;
use Illuminate\Console\Application as Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * Manages the Pollora binary mode.
 *
 * When the `pollora` binary is used (POLLORA_BINARY=true), this manager:
 * 1. Remaps `pollora:*` command signatures to shorter aliases (e.g. `pollora:make-plugin` → `make-plugin`)
 * 2. Filters the command list to only show Pollora-related commands
 *
 * Transformations are applied lazily via setArtisan() + applyIfNeeded(),
 * because commands are not yet loaded when Artisan::starting() fires.
 */
final class PolloraBinaryManager
{
    /**
     * Map of original signatures to their short aliases.
     * The original `pollora:*` signatures continue to work via hidden aliases.
     *
     * @var array<string, string>
     */
    private const array SIGNATURE_MAP = [
        'pollora:install' => 'install',
        'pollora:env-setup' => 'env-setup',
        'pollora:status' => 'status',
        'pollora:make-model' => 'make-model',
        'pollora:make-plugin' => 'make-plugin',
        'pollora:make-theme' => 'make-theme',
        'pollora:make-block' => 'make-block',
        'pollora:make-action' => 'make-action',
        'pollora:make-filter' => 'make-filter',
        'pollora:make-hook' => 'make-hook',
        'pollora:make-posttype' => 'make-posttype',
        'pollora:make-taxonomy' => 'make-taxonomy',
        'pollora:make-wp-cli' => 'make-wp-cli',
        'pollora:delete-theme' => 'delete-theme',
        'pollora:plugin:list' => 'plugin:list',
        'pollora:plugin:status' => 'plugin:status',
        'pollora:theme:status' => 'theme:status',
    ];

    private static ?Artisan $artisan = null;

    private static bool $applied = false;

    public static function isPolloraBinary(): bool
    {
        return defined('POLLORA_BINARY') && POLLORA_BINARY === true;
    }

    /**
     * @return array<string, string>
     */
    public static function getSignatureMap(): array
    {
        return self::SIGNATURE_MAP;
    }

    /**
     * Store the Artisan instance for later transformation.
     */
    public static function setArtisan(Artisan $artisan): void
    {
        self::$artisan = $artisan;
    }

    /**
     * Apply transformations if not yet applied.
     * Called at a point where all commands are loaded.
     */
    public static function applyIfNeeded(): void
    {
        if (self::$applied || ! self::$artisan instanceof Application || ! self::isPolloraBinary()) {
            return;
        }

        self::$applied = true;
        self::remapCommands(self::$artisan);
        self::filterCommands(self::$artisan);
    }

    /**
     * Remap command signatures for the Pollora binary.
     */
    public static function remapCommands(Artisan $artisan): void
    {
        /** @var array<string, SymfonyCommand> $commands */
        $commands = $artisan->all();

        foreach (self::SIGNATURE_MAP as $original => $short) {
            if (! isset($commands[$original])) {
                continue;
            }

            $command = $commands[$original];

            $command->setAliases(array_merge(
                $command->getAliases(),
                [$original]
            ));
            $command->setName($short);

            // Re-register so the application indexes the command under its new name
            $artisan->add($command);
        }
    }

    /**
     * Get the list of command names that should be visible in the Pollora binary.
     *
     * @return list<string>
     */
    public static function getVisibleCommands(): array
    {
        return [
            ...array_values(self::SIGNATURE_MAP),
            'list',
            'help',
            'completion',
        ];
    }

    /**
     * Filter commands to only show Pollora-relevant ones.
     */
    public static function filterCommands(Artisan $artisan): void
    {
        $visible = self::getVisibleCommands();

        /** @var array<string, SymfonyCommand> $commands */
        $commands = $artisan->all();

        foreach ($commands as $name => $command) {
            if (! in_array($name, $visible, true) && ! in_array($name, array_keys(self::SIGNATURE_MAP), true)) {
                $command->setHidden(true);
            }
        }
    }

    /**
     * Apply all Pollora binary transformations directly.
     */
    public static function boot(Artisan $artisan): void
    {
        if (! self::isPolloraBinary()) {
            return;
        }

        self::remapCommands($artisan);
        self::filterCommands($artisan);
    }

    /**
     * Reset state (for testing).
     */
    public static function reset(): void
    {
        self::$artisan = null;
        self::$applied = false;
    }
}
