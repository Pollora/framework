<?php

declare(strict_types=1);

namespace Pollora\Option\Infrastructure\Repositories;

use Pollora\Option\Domain\Contracts\OptionRepositoryInterface;
use Pollora\Option\Domain\Models\Option;

/**
 * WordPress implementation of the option repository.
 */
final class WordPressOptionRepository implements OptionRepositoryInterface
{
    public function get(string $key): ?Option
    {
        $value = get_option($key, null);

        if ($value === null) {
            return null;
        }

        return new Option($key, $value);
    }

    public function store(Option $option): bool
    {
        return add_option($option->key, $option->value, '', $option->autoload);
    }

    public function update(Option $option): bool
    {
        return update_option($option->key, $option->value, $option->autoload);
    }

    public function delete(string $key): bool
    {
        return delete_option($key);
    }

    public function exists(string $key): bool
    {
        return get_option($key, null) !== null;
    }
}
