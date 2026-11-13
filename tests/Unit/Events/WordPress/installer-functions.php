<?php

declare(strict_types=1);

/*
 * WordPress functions called by the installer dispatcher, declared in ITS OWN
 * namespace so that PHP resolves them before the global ones. Nothing leaks
 * into the global function table (other tests rely on wp_get_theme() and
 * friends being undefined). Each test sets the behaviour it needs with
 * Tests\Unit\Events\Fixtures\installer_wordpress().
 */

namespace Pollora\Events\WordPress\Installer {
    function pollora_test_call(string $name, array $arguments): mixed
    {
        $callback = $GLOBALS['pollora_installer_wordpress'][$name] ?? null;

        if ($callback === null) {
            throw new \LogicException("The test did not provide {$name}().");
        }

        return $callback(...$arguments);
    }

    function get_plugin_data(...$arguments): mixed
    {
        return pollora_test_call('get_plugin_data', $arguments);
    }

    function wp_get_theme(...$arguments): mixed
    {
        return pollora_test_call('wp_get_theme', $arguments);
    }

    function get_file_data(...$arguments): mixed
    {
        return pollora_test_call('get_file_data', $arguments);
    }

    function wp_clean_themes_cache(...$arguments): mixed
    {
        return pollora_test_call('wp_clean_themes_cache', $arguments);
    }
}

namespace Tests\Unit\Events\Fixtures {
    use Pollora\Events\WordPress\Installer\InstallerEventDispatcher;

    /**
     * @param  array<string, callable>  $functions
     */
    function installer_wordpress(array $functions): void
    {
        $GLOBALS['pollora_installer_wordpress'] = $functions;
    }

    class FakeTheme implements \ArrayAccess
    {
        public string $name;

        public string $version;

        /**
         * @param  array<string, string>  $headers
         */
        public function __construct(private readonly array $headers)
        {
            $this->name = $headers['Name'];
            $this->version = $headers['Version'];
        }

        public function offsetExists(mixed $offset): bool
        {
            return isset($this->headers[$offset]);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->headers[$offset];
        }

        public function offsetSet(mixed $offset, mixed $value): void {}

        public function offsetUnset(mixed $offset): void {}
    }

    class TestableInstallerDispatcher extends InstallerEventDispatcher
    {
        /** @var array<string, array<string, mixed>> */
        public array $plugins = [];

        protected function getPlugins(): array
        {
            return $this->plugins;
        }
    }
}
