<?php

declare(strict_types=1);

// Load Patchwork BEFORE anything else so Brain Monkey can redefine functions
require_once __DIR__.'/../vendor/antecedent/patchwork/Patchwork.php';

// Load test helpers (classes, utility functions)
require_once __DIR__.'/Unit/helpers.php';

require_once __DIR__.'/../vendor/autoload.php';

// TEMPORARY DEBUG (to be reverted): surface failing tests as GitHub annotations
register_shutdown_function(function (): void {
    $file = __DIR__.'/../build/debug-junit.xml';
    if (! getenv('GITHUB_ACTIONS') || ! is_file($file)) {
        return;
    }
    $xml = simplexml_load_file($file);
    $n = 0;
    foreach ($xml->xpath('//testcase[failure or error]') ?: [] as $case) {
        $node = $case->error ?? $case->failure;
        $msg = substr((string) $node, 0, 1500);
        $msg = str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $msg);
        echo '::error title='.str_replace([',', ':'], ' ', (string) $case['class'].' '.$case['name']).'::'.$msg.PHP_EOL;
        if (++$n >= 8) {
            break;
        }
    }
});
