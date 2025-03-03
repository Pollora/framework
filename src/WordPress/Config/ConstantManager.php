<?php

declare(strict_types=1);

namespace Pollora\WordPress\Config;

use Illuminate\Support\Collection;
use Pollora\WordPress\Exceptions\ConstantAlreadyDefinedException;
use Pollora\WordPress\Exceptions\UndefinedConstantException;

class ConstantManager
{
    /**
     * Collection of constants to be registered
     *
     * @var Collection<string, mixed>
     */
    protected Collection $constants;

    public function __construct()
    {
        $this->constants = collect();
    }

    /**
     * Queue a constant for definition
     *
     * @param string $key
     * @param mixed $value
     */
    public function queue(string $key, mixed $value): void
    {
        if ($this->isDefined($key)) {
            return;
        }

        $this->constants->put($key, $value);
    }

    /**
     * Retrieve a queued constant
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        if (!$this->constants->has($key)) {
            throw new UndefinedConstantException("Constant '$key' has not been queued.");
        }

        return $this->constants->get($key);
    }

    /**
     * Apply all queued constants
     */
    public function apply(): void
    {
        $this->constants->each(fn ($value, $key) => define($key, $value));

        // Clear the queue after application
        $this->constants = collect();
    }

    /**
     * Check if a constant is already defined
     *
     * @param string $key
     * @return bool
     */
    protected function isDefined(string $key): bool
    {
        return defined($key);
    }
}
