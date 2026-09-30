<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\Plugin\Application\Services\PluginRegistrar;
use Pollora\Plugin\Domain\Contracts\PluginModuleInterface;
use Pollora\Plugin\Infrastructure\Services\WordPressPluginParser;

/**
 * Where a plugin's discoverable classes are looked for.
 *
 * Themes and modules have always been scanned at `app/`, falling back to
 * `src/`. Plugins were scanned at their root — which, on any plugin with a
 * Vite build, is the directory holding `node_modules`. Measured on a demo
 * plugin: 69,741 files walked on every request to reach three PHP files, and
 * five WordPress core classes shipped inside `@wordpress/style-engine` handed
 * to discovery along the way.
 */
function registrar(): object
{
    return new class(new Container, new WordPressPluginParser) extends PluginRegistrar
    {
        /** @return list<string> */
        public function discoveryPathsFor(PluginModuleInterface $plugin): array
        {
            $paths = $this->getPluginDiscoveryPaths($plugin);
            sort($paths);

            return $paths;
        }
    };
}

function pluginAt(string $path): PluginModuleInterface
{
    $plugin = Mockery::mock(PluginModuleInterface::class);
    $plugin->shouldReceive('getPath')->andReturn($path);

    return $plugin;
}

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pollora-plugin-'.bin2hex(random_bytes(6));
    mkdir($this->root, 0o777, true);
});

afterEach(function (): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }

    rmdir($this->root);
});

describe('plugin discovery path', function (): void {
    it('scans app/ when the plugin has one', function (): void {
        mkdir($this->root.'/app');

        expect(registrar()->discoveryPathsFor(pluginAt($this->root)))->toBe([$this->root.'/app']);
    });

    it('falls back to src/', function (): void {
        mkdir($this->root.'/src');

        expect(registrar()->discoveryPathsFor(pluginAt($this->root)))->toBe([$this->root.'/src']);
    });

    it('prefers app/ over src/ when both are there', function (): void {
        mkdir($this->root.'/app');
        mkdir($this->root.'/src');

        expect(registrar()->discoveryPathsFor(pluginAt($this->root)))->toBe([$this->root.'/app']);
    });

    it('scans the other top-level directories of a plugin that has neither', function (): void {
        // A plugin keeping its classes in, say, includes/ is still discovered.
        mkdir($this->root.'/includes');
        mkdir($this->root.'/config');

        expect(registrar()->discoveryPathsFor(pluginAt($this->root)))
            ->toBe([$this->root.'/config', $this->root.'/includes']);
    });

    it('never scans a directory that cannot hold discoverable classes', function (): void {
        // A blocks-only plugin: no class, a Vite build. Its root was scanned
        // whole, node_modules included — 2 to 7 seconds on every request.
        foreach (['node_modules', 'vendor', 'build', 'dist', 'public', 'resources', 'assets', 'languages', '.git', '.github'] as $directory) {
            mkdir($this->root.'/'.$directory);
        }

        touch($this->root.'/demo-plugin.php');

        expect(registrar()->discoveryPathsFor(pluginAt($this->root)))->toBe([]);
    });

    it('never hands back a directory holding node_modules when app/ exists', function (): void {
        // The whole point: the root is where a Vite build puts node_modules.
        mkdir($this->root.'/app');
        mkdir($this->root.'/node_modules');

        expect(registrar()->discoveryPathsFor(pluginAt($this->root)))->toBe([$this->root.'/app']);
    });

    it('does not care how the path was spelled', function (): void {
        mkdir($this->root.'/app');

        expect(registrar()->discoveryPathsFor(pluginAt($this->root.'/')))->toBe([$this->root.'/app']);
    });
});
