<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Pollora\Theme\UI\Console\Commands\ThemeActivateCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @return array{0: int, 1: string, 2: array<int, string>} Exit code, output, themes switched to.
 */
function runThemeActivate(string $theme, bool $fullyLoaded): array
{
    $command = new class extends ThemeActivateCommand
    {
        public bool $fullyLoaded = true;

        /** @var array<int, string> */
        public array $switched = [];

        protected function isWordPressFullyLoaded(): bool
        {
            return $this->fullyLoaded;
        }

        protected function switchTheme(string $theme): void
        {
            $this->switched[] = $theme;
        }
    };
    $command->fullyLoaded = $fullyLoaded;
    $command->setLaravel(app());

    $input = new ArrayInput(['theme' => $theme], new InputDefinition([new InputArgument('theme')]));
    $output = new BufferedOutput;
    $command->setInput($input);
    $command->setOutput(new OutputStyle($input, $output));

    return [$command->handle(), $output->fetch(), $command->switched];
}

it('activates the given theme', function (): void {
    [$status, , $switched] = runThemeActivate('shop', fullyLoaded: true);

    expect($status)->toBe(0)->and($switched)->toBe(['shop']);
});

it('fails instead of switching theme when WordPress is not fully loaded', function (): void {
    [$status, $output, $switched] = runThemeActivate('shop', fullyLoaded: false);

    expect($status)->toBe(1)->and($switched)->toBe([])->and($output)->toContain('not fully loaded');
});
