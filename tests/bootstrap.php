<?php

declare(strict_types=1);

// Load Patchwork BEFORE anything else so Brain Monkey can redefine functions
require_once __DIR__.'/../vendor/antecedent/patchwork/Patchwork.php';

// Load test helpers (classes, utility functions)
require_once __DIR__.'/Unit/helpers.php';

require_once __DIR__.'/../vendor/autoload.php';
