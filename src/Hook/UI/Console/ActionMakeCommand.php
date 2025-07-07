<?php

declare(strict_types=1);

namespace Pollora\Hook\UI\Console;

/**
 * Class ActionMakeCommand
 *
 * Command to create a new action hook class (feature UI layer).
 * Supports generation in different locations (app, theme, plugin) through traits.
 */
class ActionMakeCommand extends AttributeMakeCommand
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'pollora:make-action';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new action hook class';

    /**
     * The type of the attribute.
     *
     * @var string
     */
    protected $type = 'Action';
}
