<?php

declare(strict_types=1);

namespace Pollora\Hook\Commands;

/**
 * Class FilterMakeCommand
 *
 * Command to create a new filter hook class.
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
