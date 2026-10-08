<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Contracts\Config\Repository;

/**
 * Make nwidart/laravel-modules' `module:make` write the lean Pollora module.
 *
 * The generator is told to write no folder, no file and no class of its stock
 * module; once it has created module.json and enabled the module, the
 * `modules.<name>.created` event writes the bundled copy of Pollora/module-default
 * over it. A project that published config/modules.php keeps nwidart's stock
 * module: its own configuration decides what the generator writes.
 *
 * These keys are read when a generator runs, after every provider registered,
 * so setting them from the framework's register() is early enough — unlike
 * `modules.activator`, which nwidart reads during its own register().
 */
class LeanModuleMake
{
    public function __construct(
        private readonly Repository $config,
        private readonly ModuleTemplate $template,
        private readonly string $publishedConfigPath,
    ) {}

    /**
     * Whether module:make writes the lean module: the project has not
     * published config/modules.php, and nwidart's configuration is loaded.
     */
    public function isActive(): bool
    {
        return ! is_file($this->publishedConfigPath) && is_array($this->config->get('modules.paths.generator'));
    }

    /**
     * Turn off every folder, file and class of nwidart's stock module.
     */
    public function applyDefaults(): void
    {
        if (! $this->isActive()) {
            return;
        }

        foreach (array_keys((array) $this->config->get('modules.paths.generator')) as $generator) {
            $this->config->set(sprintf('modules.paths.generator.%s.generate', $generator), false);
        }

        $this->config->set('modules.stubs.files', []);
        $this->config->set('modules.stubs.gitkeep', false);
    }

    /**
     * Write the lean module over what module:make created.
     *
     * @param  object  $module  The nwidart module the event carries
     */
    public function writeOver(object $module): void
    {
        if (! $this->isActive() || ! method_exists($module, 'getPath') || ! method_exists($module, 'getName')) {
            return;
        }

        $this->template->writeDefault(
            rtrim((string) $module->getPath(), '/'),
            $this->template->replacements(
                (string) $module->getName(),
                (string) $this->config->get('modules.namespace', 'Modules'),
            ),
        );
    }
}
