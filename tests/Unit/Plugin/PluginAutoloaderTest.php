<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Illuminate\Container\Container;
use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;
use Pollora\Plugin\Domain\Models\PluginModule;
use Pollora\Plugin\Infrastructure\Services\PluginAutoloader;

function removePluginAutoloaderTree(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        is_dir($path.'/'.$item) ? removePluginAutoloaderTree($path.'/'.$item) : unlink($path.'/'.$item);
    }

    rmdir($path);
}

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pollora-plugin-autoload-'.uniqid();
    mkdir($this->root.'/with-app/app', 0755, true);
    mkdir($this->root.'/with-src/src', 0755, true);
    mkdir($this->root.'/with-both/app', 0755, true);
    mkdir($this->root.'/with-both/src', 0755, true);
    mkdir($this->root.'/bare', 0755, true);

    $this->container = new Container;
    $this->autoloader = new PluginAutoloader($this->container);
});

afterEach(function (): void {
    // The loader of the container may have been registered on the autoload stack.
    $this->container->make(ModuleAutoloader::CLASS_LOADER)->unregister();
    removePluginAutoloaderTree($this->root);
});

it('keeps plugin classes under the Plugin namespace', function (): void {
    expect($this->autoloader->getPluginNamespace('acme'))->toBe('Plugin\\acme\\');
});

it('maps a plugin to its app directory, even when it also has a src directory', function (): void {
    expect($this->autoloader->registerPluginByPath('both', $this->root.'/with-both'))->toBeTrue()
        ->and($this->autoloader->getPluginSourcePath('both'))->toBe($this->root.'/with-both/app');
});

it('falls back to the src directory', function (): void {
    expect($this->autoloader->registerPluginByPath('src', $this->root.'/with-src/'))->toBeTrue()
        ->and($this->autoloader->getPluginSourcePath('src'))->toBe($this->root.'/with-src/src');
});

it('does not register a plugin without a source directory', function (): void {
    expect($this->autoloader->registerPluginByPath('bare', $this->root.'/bare'))->toBeFalse()
        ->and($this->autoloader->isPluginRegistered('bare'))->toBeFalse()
        ->and($this->autoloader->getPluginSourcePath('bare'))->toBeNull();
});

it('knows which plugins are registered', function (): void {
    $this->autoloader->registerPluginByPath('app', $this->root.'/with-app');

    expect($this->autoloader->isPluginRegistered('app'))->toBeTrue()
        ->and($this->autoloader->isPluginRegistered('other'))->toBeFalse();
});

it('lists only the Plugin namespaces', function (): void {
    $this->autoloader->registerPluginByPath('app', $this->root.'/with-app');
    $this->autoloader->registerModule(new PluginModule('theme-ish', $this->root.'/with-src'), 'theme');

    expect(array_keys($this->autoloader->getRegisteredPluginNamespaces()))->toBe(['Plugin\\app\\'])
        ->and($this->autoloader->getRegisteredNamespaces())->toHaveCount(2);
});

it('forgets a plugin that is unregistered', function (): void {
    $this->autoloader->registerPluginByPath('app', $this->root.'/with-app');
    $this->autoloader->unregisterPlugin('app');

    expect($this->autoloader->isPluginRegistered('app'))->toBeFalse();
});

it('says whether a path has an autoloadable source directory', function (): void {
    expect($this->autoloader->hasAutoloadableSource($this->root.'/with-app'))->toBeTrue()
        ->and($this->autoloader->hasAutoloadableSource($this->root.'/with-src/'))->toBeTrue()
        ->and($this->autoloader->hasAutoloadableSource($this->root.'/bare'))->toBeFalse();
});

it('prefers app over src as the autoloadable directory', function (): void {
    expect($this->autoloader->getAutoloadableSourcePath($this->root.'/with-both'))->toBe($this->root.'/with-both/app')
        ->and($this->autoloader->getAutoloadableSourcePath($this->root.'/with-src'))->toBe($this->root.'/with-src/src')
        ->and($this->autoloader->getAutoloadableSourcePath($this->root.'/bare'))->toBeNull();
});

it('makes the classes of a registered plugin loadable', function (): void {
    $class = 'PluginAutoloaderFixture'.str_replace('.', '', uniqid('', true));
    $module = new PluginModule($class, $this->root.'/with-app');
    file_put_contents($this->root.'/with-app/app/Greeter.php', '<?php namespace Plugin\\'.$class.'; final class Greeter { public function hello(): string { return "hi"; } }');

    $this->autoloader->registerPluginModule($module);

    $greeter = 'Plugin\\'.$class.'\\Greeter';

    expect(class_exists($greeter))->toBeTrue()
        ->and((new $greeter)->hello())->toBe('hi');
});

it('registers only the plugin modules of a list', function (): void {
    $first = new PluginModule('first', $this->root.'/with-app');
    $second = new PluginModule('second', $this->root.'/with-src');

    $this->autoloader->registerPlugins([$first, 'not a module', new stdClass, $second]);

    expect(array_keys($this->autoloader->getRegisteredPluginNamespaces()))->toBe(['Plugin\\First\\', 'Plugin\\Second\\'])
        ->and($this->container->make(ModuleAutoloader::CLASS_LOADER))->toBeInstanceOf(ClassLoader::class);
});
