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
    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Nettoyage si nécessaire
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Nettoyage si nécessaire
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::PRE_AUTOLOAD_DUMP => [
                ['onPreAutoloadDump', 0]
            ]
        ];
    }

    public function onPreAutoloadDump(): void
    {
        $this->io->write('<info>Pollora Helper Loader: Loading helpers...</info>');

        $autoloadFile = $this->getAutoloadPath();

        if (! is_file($autoloadFile)) {
            $this->io->write('<error>Autoload file not found</error>');
            return;
        }

        $helpers = $this->getHelperFiles();

        if (empty($helpers)) {
            $this->io->write('<comment>No helper files found in composer.json</comment>');
            return;
        }

        $this->injectHelpers($autoloadFile, $helpers);

        $this->io->write('<info>Helper files injected successfully</info>');
    }

    private function getHelperFiles(): array
    {
        $autoload = $this->composer->getPackage()->getAutoload();
        return $autoload['files'] ?? [];
    }

    private function getAutoloadPath(): string
    {
        return $this->composer->getConfig()->get('vendor-dir') . '/autoload.php';
    }

    private function injectHelpers(string $autoloadFile, array $helpers): void
    {
        $content = file_get_contents($autoloadFile);
        $requires = [];

        foreach ($helpers as $helper) {
            $absolutePath = $this->getAbsolutePath($helper);
            if (! str_contains($content, $absolutePath)) {
                $requires[] = sprintf("require_once '%s';", $absolutePath);
            }
        }

        if (! empty($requires)) {
            file_put_contents(
                $autoloadFile,
                str_replace('<?php', "<?php\n" . implode("\n", $requires), $content)
            );
        }
    }

    private function getAbsolutePath(string $helperPath): string
    {
        return dirname($this->getAutoloadPath(), 2) . '/' . $helperPath;
    }
}
