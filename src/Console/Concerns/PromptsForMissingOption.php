<?php

declare(strict_types=1);

namespace Pollora\Console\Concerns;

use Closure;
use Illuminate\Support\Collection;
use Pollora\Console\Contracts\PromptsForMissingOption as PromptsForMissingOptionContract;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Trait for prompting missing options in console commands.
 *
 * This trait works similarly to Laravel's PromptsForMissingInput trait
 * but specifically handles missing command options instead of arguments.
 *
 * Supported prompt types:
 * - 'text' (default): Text input with validation
 * - 'confirm': Yes/No confirmation prompt
 * - 'select': Selection from predefined options
 *
 * Configuration format:
 * [
 *     'option-name' => [
 *         'label' => 'Prompt message',
 *         'type' => 'confirm|select|text',
 *         'default' => mixed,
 *         'validation' => 'required|url|...',
 *         'options' => [] // For select type only
 *     ]
 * ]
 */
trait PromptsForMissingOption
{
    /**
     * Interact with the user before validating the input.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        if ($this instanceof PromptsForMissingOptionContract) {
            $this->promptForMissingOptions($input, $output);
        }
    }

    /**
     * Prompt the user for any missing options.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function promptForMissingOptions(InputInterface $input, OutputInterface $output): void
    {
        $promptConfiguration = $this->promptForMissingOptionsUsing();

        if (empty($promptConfiguration)) {
            return;
        }

        $prompted = (new Collection($this->getDefinition()->getOptions()))
            ->reject(fn (InputOption $option) => $option->getName() === 'help' || $option->getName() === 'quiet' || $option->getName() === 'verbose' || $option->getName() === 'version' || $option->getName() === 'ansi' || $option->getName() === 'no-ansi' || $option->getName() === 'no-interaction')
            ->filter(fn (InputOption $option) => array_key_exists($option->getName(), $promptConfiguration))
            ->filter(fn (InputOption $option) => match (true) {
                $option->isArray() => empty($input->getOption($option->getName())),
                default => is_null($input->getOption($option->getName())) || $input->getOption($option->getName()) === false,
            })
            ->each(function (InputOption $option) use ($input, $promptConfiguration) {
                $optionName = $option->getName();
                $configuration = $promptConfiguration[$optionName];

                // Handle closure configuration
                if ($configuration instanceof Closure) {
                    $value = $configuration();
                    $input->setOption($optionName, $option->isArray() ? [$value] : $value);
                    return;
                }

                // Handle array configuration [label, default, validation]
                if (is_array($configuration)) {
                    $label = $configuration['label'] ?? $configuration[0] ?? 'What is the ' . str_replace('-', ' ', $optionName) . '?';
                    $default = $configuration['default'] ?? $configuration[1] ?? '';
                    $validation = $configuration['validation'] ?? $configuration[2] ?? null;
                    $type = $configuration['type'] ?? 'text';
                    $options = $configuration['options'] ?? [];
                } else {
                    // Handle string configuration (just the label)
                    $label = $configuration;
                    $default = '';
                    $validation = null;
                    $type = 'text';
                    $options = [];
                }

                $answer = match ($type) {
                    'confirm' => confirm(
                        label: $label,
                        default: $default
                    ),
                    'select' => select(
                        label: $label,
                        options: $options,
                        default: $default
                    ),
                    default => text(
                        label: $label,
                        default: $default,
                        validate: $this->buildValidationClosure($validation, $optionName)
                    )
                };

                $input->setOption($optionName, $option->isArray() ? [$answer] : $answer);
            })
            ->isNotEmpty();

        if ($prompted) {
            $this->afterPromptingForMissingOptions($input, $output);
        }
    }

    /**
     * Build validation closure from string or existing closure.
     *
     * @param mixed $validation
     * @param string $optionName
     * @return Closure|null
     */
    protected function buildValidationClosure($validation, string $optionName): ?Closure
    {
        if ($validation instanceof Closure) {
            return $validation;
        }

        if (is_string($validation)) {
            return function ($value) use ($validation, $optionName) {
                $rules = explode('|', $validation);

                foreach ($rules as $rule) {
                    if ($rule === 'required' && empty($value)) {
                        return "The {$optionName} is required.";
                    }

                    if ($rule === 'url' && !empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                        return "The {$optionName} must be a valid URL.";
                    }
                }

                return null;
            };
        }

        return null;
    }

    /**
     * Prompt for missing input options using the returned questions.
     *
     * @return array
     */
    protected function promptForMissingOptionsUsing(): array
    {
        return [];
    }

    /**
     * Perform actions after the user was prompted for missing options.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function afterPromptingForMissingOptions(InputInterface $input, OutputInterface $output): void
    {
        //
    }
}
