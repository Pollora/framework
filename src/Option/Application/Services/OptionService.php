<?php

declare(strict_types=1);

namespace Pollora\Option\Application\Services;

use Pollora\Option\Domain\Contracts\OptionRepositoryInterface;
use Pollora\Option\Domain\Models\Option;
use Pollora\Option\Domain\Services\OptionValidationService;

/**
 * Application service for managing WordPress options.
 */
final class OptionService
{
    public function __construct(
        private readonly OptionRepositoryInterface $repository,
        private readonly OptionValidationService $validator
    ) {}

    /**
     * Retrieve option value with type safety.
     *
     * @param  string  $key  Option key
     * @param  mixed  $default  Default value if option doesn't exist
     * @return mixed Option value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validator->validateKey($key);

        $option = $this->repository->get($key);

        return $option?->value ?? $default;
    }

    /**
     * Create or update an option.
     *
     * @param  string  $key  Option key
     * @param  mixed  $value  Option value
     * @return bool True on success, false on failure
     */
    public function set(string $key, mixed $value): bool
    {
        $this->validator->validateKey($key);
        $this->validator->validateValue($value);

        $option = new Option($key, $value);

        if ($this->repository->exists($key)) {
            return $this->repository->update($option);
        }

        return $this->repository->store($option);
    }

    /**
     * Update an existing option.
     *
     * @param  string  $key  Option key
     * @param  mixed  $value  Option value
     * @return bool True on success, false on failure
     */
    public function update(string $key, mixed $value): bool
    {
        $this->validator->validateKey($key);
        $this->validator->validateValue($value);

        $option = new Option($key, $value);

        return $this->repository->update($option);
    }

    /**
     * Delete an option.
     *
     * @param  string  $key  Option key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool
    {
        $this->validator->validateKey($key);

        return $this->repository->delete($key);
    }

    /**
     * Check if an option exists.
     *
     * @param  string  $key  Option key
     * @return bool True if the option exists, false otherwise
     */
    public function exists(string $key): bool
    {
        $this->validator->validateKey($key);

        return $this->repository->exists($key);
    }
}
