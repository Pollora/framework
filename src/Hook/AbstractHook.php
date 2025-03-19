<?php

declare(strict_types=1);

namespace Pollora\Hook;

use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

/**
 * Base abstract class for WordPress hooks (actions and filters).
 *
 * Provides common implementation details and features to avoid code duplication.
 *
 * Features:
 * - Automatic detection of accepted arguments using PHP Reflection
 * - Support for class references without explicit method names
 * - Smart hook name to method name conversion
 */
abstract class AbstractHook implements Hookable
{
    /**
     * Stores indexed hooks for efficient lookup and removal.
     *
     * Structure:
     * [
     *    'hook_name' => [
     *        priority => [callback1, callback2, ...]
     *    ]
     * ]
     *
     * @var array<string, array<int, array<callable|string|array>>>
     */
    protected array $indexedHooks = [];

    /**
     * The WordPress function to use when adding a hook.
     * To be implemented by child classes.
     *
     * @param  string  $hook  Hook name
     * @param  callable|string|array  $callback  Callback to execute
     * @param  int  $priority  Priority
     * @param  int  $acceptedArgs  Number of accepted args
     */
    abstract protected function addHook(string $hook, callable|string|array $callback, int $priority, int $acceptedArgs): void;

    /**
     * The WordPress function to use when removing a hook.
     * To be implemented by child classes.
     *
     * @param  string  $hook  Hook name
     * @param  callable|string|array  $callback  Callback to remove
     * @param  int  $priority  Priority
     */
    abstract protected function removeHook(string $hook, callable|string|array $callback, int $priority): void;

    /**
     * Adds a callback to a WordPress hook.
     *
     * Uses reflection to automatically detect the number of arguments
     * the callback can accept if $acceptedArgs is null.
     *
     * Supports class references without explicit method names, using Laravel naming conventions.
     * For example, for a hook named 'the_content', it will look for a method named 'theContent'.
     *
     * @param  string  $hook  The name of the WordPress hook.
     * @param  callable|string|array  $callback  The callback function or class to be executed.
     * @param  int  $priority  Optional. The priority order in which the function will be executed.
     *                         Default 10.
     * @param  int|null  $acceptedArgs  Optional. The number of arguments the function accepts.
     *                                  If null, it will be auto-detected. Default null.
     *
     * @throws InvalidArgumentException If a class name is provided and the corresponding method cannot be found.
     */
    public function add(string $hook, callable|string|array $callback, int $priority = 10, ?int $acceptedArgs = null): void
    {
        // Handle class reference without method name
        if (is_string($callback) && class_exists($callback)) {
            $callbackArray = $this->resolveClassCallback($callback, $hook);
            $callback = $callbackArray;
        }

        // Auto-detect accepted args if not specified
        if ($acceptedArgs === null) {
            $acceptedArgs = $this->detectAcceptedArgs($callback);
        }

        $this->addHook($hook, $callback, $priority, $acceptedArgs);
        $this->registerCallback($hook, $callback, $priority);
    }

    /**
     * Resolves a class name to a [instance, method] callback array.
     *
     * Follows Laravel naming conventions for method names based on hook names.
     *
     * @param  string  $className  The name of the class
     * @param  string  $hook  The WordPress hook name
     * @return array A [instance, method] callback
     *
     * @throws InvalidArgumentException If the method doesn't exist in the class
     */
    protected function resolveClassCallback(string $className, string $hook): array
    {
        // Convert hook name to method name using Laravel conventions
        $methodName = $this->hookToMethodName($hook);

        // Create instance
        $instance = new $className;

        // Verify the method exists
        try {
            $reflection = new ReflectionClass($className);
            if (! $reflection->hasMethod($methodName)) {
                throw new InvalidArgumentException(
                    sprintf('Method %s does not exist in class %s', $methodName, $className)
                );
            }
        } catch (ReflectionException $e) {
            throw new InvalidArgumentException(
                sprintf('Error resolving class callback: %s', $e->getMessage())
            );
        }

        return [$instance, $methodName];
    }

    /**
     * Converts a WordPress hook name to a method name using Laravel conventions.
     *
     * Example: 'the_content' becomes 'theContent'
     *
     * @param  string  $hook  The hook name
     * @return string The method name
     */
    protected function hookToMethodName(string $hook): string
    {
        // Replace all non-alphanumeric characters with underscores
        $normalized = preg_replace('/[^\w]/', '_', $hook);

        // Convert to camel case and ensure first letter is lowercase
        return lcfirst(Str::studly($normalized));
    }

    /**
     * Removes a callback from a WordPress hook.
     *
     * @param  string  $hook  The name of the WordPress hook.
     * @param  callable|string|array  $callback  The callback function to remove.
     * @param  int  $priority  Optional. The priority of the function to remove.
     *                         Default 10.
     */
    public function remove(string $hook, callable|string|array $callback, int $priority = 10): void
    {
        // Handle class reference without method name
        if (is_string($callback) && class_exists($callback)) {
            try {
                $callbackArray = $this->resolveClassCallback($callback, $hook);
                $callback = $callbackArray;
            } catch (InvalidArgumentException $e) {
                // If the method doesn't exist, there's nothing to remove
                return;
            }
        }

        $this->removeHook($hook, $callback, $priority);
        $this->unregisterCallback($hook, $callback, $priority);
    }

    /**
     * Checks if a hook, or specific callback on that hook, exists.
     *
     * @param  string  $hook  The name of the WordPress hook.
     * @param  callable|string|array|null  $callback  Optional. The callback to check for.
     *                                                If null, only checks if the hook exists.
     * @param  int|null  $priority  Optional. The priority of the function to check for.
     *                              If null, checks all priorities.
     * @return bool True if the hook or specified callback exists, false otherwise.
     */
    public function exists(string $hook, callable|string|array|null $callback = null, ?int $priority = null): bool
    {
        // Check if hook exists at all
        if (! isset($this->indexedHooks[$hook])) {
            return false;
        }

        // Just checking if hook exists (no callback specified)
        if ($callback === null) {
            return true;
        }

        // Handle class reference without method name
        if (is_string($callback) && class_exists($callback)) {
            try {
                $callbackArray = $this->resolveClassCallback($callback, $hook);
                $callback = $callbackArray;
            } catch (InvalidArgumentException $e) {
                // If the method doesn't exist, it can't be registered
                return false;
            }
        }

        $id = $this->getCallbackId($callback);

        // Check at specific priority
        if ($priority !== null) {
            return isset($this->indexedHooks[$hook][$priority][$id]);
        }

        // Check at any priority
        foreach ($this->indexedHooks[$hook] as $priorityCallbacks) {
            if (isset($priorityCallbacks[$id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieves all callbacks registered for a specific WordPress hook.
     *
     * @param  string  $hook  The hook name.
     * @param  int|null  $priority  Optional. The specific priority to get callbacks for.
     * @return array The registered callbacks, or an empty array if none exist.
     */
    public function getCallbacks(string $hook, ?int $priority = null): array
    {
        global $wp_filter;

        if (! isset($wp_filter[$hook])) {
            return [];
        }

        if ($priority !== null && isset($wp_filter[$hook]->callbacks[$priority])) {
            return $wp_filter[$hook]->callbacks[$priority];
        }

        return $wp_filter[$hook]->callbacks ?? [];
    }

    /**
     * Generates a unique identifier for a callback.
     *
     * This is used to properly index callbacks that might not be directly comparable.
     *
     * @param  callable|string|array  $callback  The callback to generate an identifier for.
     * @return string A unique identifier for the callback.
     */
    protected function getCallbackId(callable|string|array $callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback) && count($callback) === 2) {
            if (is_object($callback[0])) {
                return get_class($callback[0]).'->'.$callback[1];
            }

            return $callback[0].'::'.$callback[1];
        }

        if ($callback instanceof \Closure) {
            return spl_object_hash($callback);
        }

        return serialize($callback);
    }

    /**
     * Determines the number of arguments a callback can accept using PHP Reflection.
     *
     * @param  callable|string|array  $callback  The callback to analyze.
     * @return int The maximum number of arguments the callback accepts.
     */
    protected function detectAcceptedArgs(callable|string|array $callback): int
    {
        $reflection = null;

        // Handle various callback types
        if (is_string($callback) && function_exists($callback)) {
            // Named function
            $reflection = new \ReflectionFunction($callback);
        } elseif (is_array($callback) && count($callback) === 2) {
            // Class method (static or instance)
            try {
                $reflection = new \ReflectionMethod($callback[0], $callback[1]);
            } catch (\ReflectionException $e) {
                // Fall back to default if reflection fails
                return 1;
            }
        } elseif ($callback instanceof \Closure) {
            // Closure
            $reflection = new \ReflectionFunction($callback);
        } elseif (is_object($callback) && method_exists($callback, '__invoke')) {
            // Invokable object
            try {
                $reflection = new \ReflectionMethod($callback, '__invoke');
            } catch (\ReflectionException $e) {
                // Fall back to default if reflection fails
                return 1;
            }
        } else {
            // Unknown callback type
            return 1;
        }

        if ($reflection) {
            $params = $reflection->getParameters();

            // Check if the function uses variadic parameters
            foreach ($params as $param) {
                if ($param->isVariadic()) {
                    // Function can accept any number of parameters
                    return PHP_INT_MAX;
                }
            }

            // Return the total number of parameters
            return count($params);
        }

        // Default to 1 if reflection didn't work
        return 1;
    }

    /**
     * Registers a callback in the internal index.
     *
     * @param  string  $hook  The hook name.
     * @param  callable|string|array  $callback  The callback to register.
     * @param  int  $priority  The priority of the callback.
     */
    protected function registerCallback(string $hook, callable|string|array $callback, int $priority): void
    {
        $id = $this->getCallbackId($callback);
        $this->indexedHooks[$hook][$priority][$id] = $callback;
    }

    /**
     * Unregisters a callback from the internal index.
     *
     * @param  string  $hook  The hook name.
     * @param  callable|string|array  $callback  The callback to unregister.
     * @param  int  $priority  The priority of the callback.
     */
    protected function unregisterCallback(string $hook, callable|string|array $callback, int $priority): void
    {
        $id = $this->getCallbackId($callback);

        if (isset($this->indexedHooks[$hook][$priority][$id])) {
            unset($this->indexedHooks[$hook][$priority][$id]);

            // Clean up empty arrays
            if (empty($this->indexedHooks[$hook][$priority])) {
                unset($this->indexedHooks[$hook][$priority]);

                if (empty($this->indexedHooks[$hook])) {
                    unset($this->indexedHooks[$hook]);
                }
            }
        }
    }
}
