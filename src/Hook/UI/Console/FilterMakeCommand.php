<?php

// Relocated from Commands/FilterMakeCommand.php. Content will be copied verbatim and namespace updated.

declare(strict_types=1);

namespace Pollora\Hook\UI\Console;

/**
 * Class FilterMakeCommand
 *
 * Command to create a new filter hook class (feature UI layer).
 * Supports generation in different locations (app, theme, plugin) through traits.
 * 
 * @package Pollora\Hook\UI\Console
 */
class FilterMakeCommand extends AttributeMakeCommand
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'pollora:make-filter';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new filter hook class';

    /**
     * The type of the attribute.
     *
     * @var string
     */
    protected $type = 'Filter';
}
