<?php

declare(strict_types=1);

use Pollora\Modules\Infrastructure\Services\ModuleScaffolderService;
use Pollora\Plugin\UI\Console\MakePluginCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

describe('pollora:make:plugin', function (): void {
    it('leaves the template packaging tooling in bin/ out of the new plugin', function (): void {
        $pluginsPath = sys_get_temp_dir().'/pollora-make-plugin-'.uniqid();
        config()->set('plugin.path', $pluginsPath);

        $scaffolder = Mockery::mock(ModuleScaffolderService::class);
        $scaffolder->shouldReceive('downloadAndScaffold')
            ->once()
            ->withArgs(fn (...$arguments): bool => ($arguments['removeDirs'] ?? $arguments[7] ?? null) === ['bin'])
            ->andReturnTrue();
        $this->app->instance(ModuleScaffolderService::class, $scaffolder);

        $command = $this->app->make(MakePluginCommand::class);
        $command->setLaravel($this->app);

        $input = new ArrayInput(['name' => 'acme', '--repository' => 'pollora/plugin-default']);
        $input->setInteractive(false);

        expect($command->run($input, new BufferedOutput))->toBe(0);
    });
});
