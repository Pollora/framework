<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Console\OutputStyle;
use Pollora\Plugin\UI\Console\Commands\PluginActivateCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Run the activate command with a stand-in for activate_plugin().
 *
 * @param  array<string, mixed>  $results  What the stand-in returns, by plugin file
 * @return array{0: int, 1: string, 2: list<string>}
 */
function runPluginActivateWithResults(array $plugins, array $results): array
{
    $command = new class($results) extends PluginActivateCommand
    {
        /** @var list<string> */
        public array $attempted = [];

        public function __construct(private readonly array $results)
        {
            parent::__construct();
        }

        protected function isWordPressFullyLoaded(): bool
        {
            return true;
        }

        protected function activatePlugin(string $pluginFile): mixed
        {
            $this->attempted[] = $pluginFile;

            return $this->results[$pluginFile] ?? null;
        }
    };
    $command->setLaravel(app());

    $input = new ArrayInput(['plugins' => $plugins], new InputDefinition([new InputArgument('plugins', InputArgument::IS_ARRAY)]));
    $output = new BufferedOutput;
    $command->setInput($input);
    $command->setOutput(new OutputStyle($input, $output));

    return [$command->handle(), $output->fetch(), $command->attempted];
}

it('reports a plugin WordPress refuses to activate, and still tries the others', function (): void {
    $error = new class
    {
        public function get_error_message(): string
        {
            return 'Plugin file does not exist.';
        }
    };
    Functions\when('is_wp_error')->alias(fn ($thing): bool => $thing === $error);

    [$status, $output, $attempted] = runPluginActivateWithResults(
        ['broken/broken.php', 'good/good.php'],
        ['broken/broken.php' => $error],
    );

    expect($status)->toBe(1)
        ->and($attempted)->toBe(['broken/broken.php', 'good/good.php'])
        ->and($output)->toContain('Failed to activate "broken/broken.php": Plugin file does not exist.')
        ->and($output)->toContain('Plugin "good/good.php" activated.');
});

it('succeeds when every plugin activates', function (): void {
    Functions\when('is_wp_error')->justReturn(false);

    [$status, $output] = runPluginActivateWithResults(['a/a.php', 'b/b.php'], []);

    expect($status)->toBe(0)
        ->and($output)->toContain('Plugin "a/a.php" activated.')
        ->and($output)->toContain('Plugin "b/b.php" activated.');
});

it('does not consider WordPress loaded until plugins_loaded has run', function (): void {
    $command = new class extends PluginActivateCommand
    {
        public function loaded(): bool
        {
            return $this->isWordPressFullyLoaded();
        }
    };

    Functions\when('did_action')->justReturn(0);

    expect($command->loaded())->toBeFalse();
});
