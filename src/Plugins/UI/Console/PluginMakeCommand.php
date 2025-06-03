<?php

declare(strict_types=1);

namespace Pollora\Plugins\UI\Console;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Pollora\Modules\UI\Console\ModuleMakeCommand;

class PluginMakeCommand extends ModuleMakeCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'pollora:make-plugin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new WordPress plugin boilerplate';

    /**
     * Execute the console command.
     *
     *
     * @throws FileNotFoundException
     */
    public function handle(): ?bool
    {
        $this->components->info(sprintf('Not yet implemented.'));
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/index.stub';
    }
}
