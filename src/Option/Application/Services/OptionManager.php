<?php

declare(strict_types=1);

namespace Pollora\Option\Application\Services;

use Pollora\Option\Application\Service\OptionService;
use Pollora\Option\Domain\Contracts\OptionManagerInterface;

/**
 * Option manager of the framework: the `pollora/option` service plus the
 * Laravel-style `forget()` alias, so that the Option facade carries no logic.
 */
final readonly class OptionManager implements OptionManagerInterface
{
    public function __construct(private OptionService $options) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->options->get($key, $default);
    }

    public function set(string $key, mixed $value): bool
    {
        return $this->options->set($key, $value);
    }

    public function update(string $key, mixed $value): bool
    {
        return $this->options->update($key, $value);
    }

    public function delete(string $key): bool
    {
        return $this->options->delete($key);
    }

    public function exists(string $key): bool
    {
        return $this->options->exists($key);
    }

    public function forget(string $key): bool
    {
        return $this->delete($key);
    }
}
