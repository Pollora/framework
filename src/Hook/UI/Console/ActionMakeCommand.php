<?php

declare(strict_types=1);

namespace Pollora\Hook\UI\Console;

use Illuminate\Console\Attributes\Aliases;
use Illuminate\Console\Attributes\Description;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class ActionMakeCommand
 *
 * Command to create a new action hook class (feature UI layer).
 * Supports generation in different locations (app, theme, plugin) through traits.
 */
#[Description('Create a new action hook class')]
#[Aliases(['pollora:make-action'])]
class ActionMakeCommand extends AttributeMakeCommand
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'pollora:make:action';

    /**
     * The type of the attribute.
     *
     * @var string
     */
    protected $type = 'Action';

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            ['async', null, InputOption::VALUE_NONE, 'Run the method after the request, with #[Async]'],
        ];
    }
}
