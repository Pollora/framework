<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Pollora\Plugin\UI\Console\Commands\PluginActivateCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\BufferedOutput;

function runPluginActivate(array $plugins, bool $fullyLoaded): array
{
    $command = new class extends PluginActivateCommand
    {
        public bool $fullyLoaded = true;

        /** @var array<int, string> */
        public array $activated = [];

        protected function isWordPressFullyLoaded(): bool
        {
            return $this->fullyLoaded;
        }

        protected function activatePlugin(string $pluginFile): mixed
        {
            $this->activated[] = $pluginFile;

            return null;
        }
    };
    $command->fullyLoaded = $fullyLoaded;
    $command->setLaravel(app());

    $input = new ArrayInput(['plugins' => $plugins], new InputDefinition([new InputArgument('plugins', InputArgument::IS_ARRAY)]));
    $output = new BufferedOutput;
    $command->setInput($input);
    $command->setOutput(new OutputStyle($input, $output));

    return [$command->handle(), $output->fetch(), $command->activated];
}

it('activates the given plugins', function (): void {
    [$status, , $activated] = runPluginActivate(['woocommerce/woocommerce.php'], fullyLoaded: true);

    expect($status)->toBe(0)->and($activated)->toBe(['woocommerce/woocommerce.php']);
});

it('fails instead of activating when WordPress is not fully loaded', function (): void {
    [$status, $output, $activated] = runPluginActivate(['woocommerce/woocommerce.php'], fullyLoaded: false);

    expect($status)->toBe(1)->and($activated)->toBe([])->and($output)->toContain('not fully loaded');
});
