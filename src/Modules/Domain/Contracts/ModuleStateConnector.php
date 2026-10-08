<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Contracts;

/**
 * Where the enabled state of Laravel modules lives.
 *
 * nwidart/laravel-modules asks for it during Laravel's register phase, before
 * WordPress loads: a connector reads with what exists then (files, Laravel's
 * database connection, configuration), never with WordPress functions.
 */
interface ModuleStateConnector
{
    /**
     * @return array<string, bool> State by module name; a module missing from it is disabled
     */
    public function all(): array;

    public function set(string $module, bool $enabled): void;

    public function forget(string $module): void;

    /**
     * Whether a toggle can be written now (file writable, database reachable).
     */
    public function writable(): bool;

    /**
     * Whether a written state survives a deployment.
     */
    public function persistent(): bool;

    /**
     * Where the state lives, for the Plugins screen: "JSON file", "Database".
     */
    public function label(): string;
}
