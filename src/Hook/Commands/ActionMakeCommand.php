<?php

namespace Pollora\Hook\Commands;

class ActionMakeCommand extends AttributeMakeCommand
{
    protected $name = 'make:action';
    protected $description = 'Create a new action hook class';
    protected $type = 'Action';
}
