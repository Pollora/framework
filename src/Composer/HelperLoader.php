<?php

declare(strict_types=1);

namespace Pollora\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

final class HelperLoader implements PluginInterface, EventSubscriberInterface
{
    private ?Composer $composer = null;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}
    public function uninstall(Composer $composer, IOInterface $io): void {}

    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::PRE_AUTOLOAD_DUMP => 'load'];
    }

    public function load(): bool
    {
        if (! $this->composer || ! $this->shouldInject()) {
            return false;
        }

        $this->injectHelpers($this->getHelperFiles());

        return true;
    }

    private function shouldInject(): bool
    {
        return is_file($this->autoloadPath());
    }

    private function injectHelpers(array $helperFiles): void
    {
        if (empty($helperFiles)) {
            return;
        }

        $autoloadContent = file_get_contents($this->autoloadPath());
        $injections = [];

        foreach ($helperFiles as $file) {
            $injection = "require_once '{$file}';";
            if (! str_contains($autoloadContent, $injection)) {
                $injections[] = $injection;
            }
        }

        if (! empty($injections)) {
            file_put_contents(
                $this->autoloadPath(),
                str_replace(
                    '<?php',
                    "<?php\n" . implode("\n", $injections),
                    $autoloadContent
                )
            );
        }
    }

    private function getHelperFiles(): array
    {
        $package = $this->composer->getPackage();
        $autoload = $package->getAutoload();
        $files = $autoload['files'] ?? [];

        // Convertir les chemins relatifs en chemins absolus
        return array_map(
            fn(string $file): string => dirname($this->autoloadPath(), 2) . '/' . $file,
            $files
        );
    }

    private function autoloadPath(): string
    {
        return dirname(__DIR__, 4) . '/autoload.php';
    }
}
