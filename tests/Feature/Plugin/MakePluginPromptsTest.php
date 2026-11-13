<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Console\OutputStyle;
use Illuminate\Filesystem\Filesystem;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\TextPrompt;
use Pollora\Modules\Infrastructure\Services\ModuleScaffolderService;
use Pollora\Plugin\UI\Console\MakePluginCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * pollora:make:plugin when it has to ask: the answers come from the prompts' fallbacks.
 */
function makePluginAsking(ModuleScaffolderService $scaffolder, array $input): array
{
    $command = new class(new Repository, new Filesystem, $scaffolder) extends MakePluginCommand
    {
        protected function getPluginsPath(): string
        {
            return sys_get_temp_dir().'/pollora-make-plugin-prompts-never-created';
        }
    };
    $command->setDefinition((new MakePluginCommand(new Repository, new Filesystem, $scaffolder))->getDefinition());
    $command->setLaravel(app());

    $input = new ArrayInput($input, $command->getDefinition());
    $input->setInteractive(false);

    $output = new BufferedOutput;
    $command->setInput($input);
    $command->setOutput(new OutputStyle($input, $output));
    (new ReflectionProperty($command, 'plugin'))->setValue($command, (new ReflectionMethod($command, 'makePlugin'))->invoke($command, $input->getArgument('name')));

    return [$command, $output];
}

beforeEach(function (): void {
    // The fallbacks are static: put them back as they were.
    $this->shouldFallback = new ReflectionProperty(Prompt::class, 'shouldFallback');
    $this->fallbacks = new ReflectionProperty(Prompt::class, 'fallbacks');
    $this->previousShouldFallback = $this->shouldFallback->getValue();
    $this->previousFallbacks = $this->fallbacks->getValue();

    Prompt::fallbackWhen(true);
    $this->scaffolder = Mockery::mock(ModuleScaffolderService::class);
});

afterEach(function (): void {
    $this->shouldFallback->setValue(null, $this->previousShouldFallback);
    $this->fallbacks->setValue(null, $this->previousFallbacks);
});

it('offers the default template when asked how to create the plugin, and answered so', function (): void {
    SelectPrompt::fallbackUsing(function (SelectPrompt $prompt): string {
        expect($prompt->label)->toBe('How would you like to create the plugin?');

        return 'default';
    });
    [$command] = makePluginAsking($this->scaffolder, ['name' => 'acme']);

    expect((new ReflectionMethod($command, 'promptForRepository'))->invoke($command))->toBeNull();
});

it('asks for the repository when told to download one', function (): void {
    SelectPrompt::fallbackUsing(fn (): string => 'repository');
    TextPrompt::fallbackUsing(fn (): string => 'acme/starter');
    [$command] = makePluginAsking($this->scaffolder, ['name' => 'acme']);

    expect((new ReflectionMethod($command, 'promptForRepository'))->invoke($command))->toBe('acme/starter');
});

it('only accepts a repository in owner/repo form', function (): void {
    $verdicts = [];
    SelectPrompt::fallbackUsing(fn (): string => 'repository');
    TextPrompt::fallbackUsing(function (TextPrompt $prompt) use (&$verdicts): string {
        $validate = (fn (): mixed => $this->validate)->call($prompt);
        $verdicts = [$validate(''), $validate('no-slash'), $validate('owner/repo')];

        return 'owner/repo';
    });
    [$command] = makePluginAsking($this->scaffolder, ['name' => 'acme']);

    (new ReflectionMethod($command, 'promptForRepository'))->invoke($command);

    expect($verdicts)->toBe(['Repository is required', 'Repository must be in owner/repo format', null]);
});

it('does not ask about the repository when one is given', function (): void {
    SelectPrompt::fallbackUsing(function (): string {
        throw new RuntimeException('should not be asked');
    });
    [$command] = makePluginAsking($this->scaffolder, ['name' => 'acme', '--repository' => 'acme/starter']);

    expect((new ReflectionMethod($command, 'promptForRepository'))->invoke($command))->toBe('acme/starter');
});

it('asks whether to activate the plugin when --activate-plugin is not given, and leaves WordPress alone on no', function (): void {
    $asked = [];
    SelectPrompt::fallbackUsing(function (SelectPrompt $prompt) use (&$asked): string {
        $asked[] = [$prompt->label, $prompt->default];

        return 'no';
    });
    [$command, $output] = makePluginAsking($this->scaffolder, ['name' => 'acme']);

    (new ReflectionMethod($command, 'promptAndActivatePlugin'))->invoke($command);

    expect($asked)->toBe([['Do you want to activate "acme" plugin?', 'yes']])
        ->and($output->fetch())->not->toContain('activated')->not->toContain('Unable to activate');
});

it('does not ask, and leaves WordPress alone, when --activate-plugin=no', function (): void {
    SelectPrompt::fallbackUsing(function (): string {
        throw new RuntimeException('should not be asked');
    });
    [$command, $output] = makePluginAsking($this->scaffolder, ['name' => 'acme', '--activate-plugin' => 'no']);

    (new ReflectionMethod($command, 'promptAndActivatePlugin'))->invoke($command);

    expect($output->fetch())->toBe('');
});
