<?php

namespace Pollora\Hook\Commands;

class FilterMakeCommand extends AttributeMakeCommand
{
    protected $name = 'make:filter';
    protected $description = 'Create a new filter hook class';
    protected $type = 'Filter';
}
