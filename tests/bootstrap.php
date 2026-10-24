<?php

declare(strict_types=1);

// Load Patchwork BEFORE anything else so Brain Monkey can redefine functions
require_once __DIR__.'/../vendor/antecedent/patchwork/Patchwork.php';

// Load test helpers (classes, utility functions)
require_once __DIR__.'/Unit/helpers.php';

require_once __DIR__.'/../vendor/autoload.php';

// TEMPORARY DEBUG (to be reverted): surface failing tests as GitHub annotations
register_shutdown_function(function (): void {
    if (! getenv('GITHUB_ACTIONS')) {
        return;
    }
    $file = __DIR__.'/../build/debug-junit.xml';
    $err = error_get_last();
    fwrite(STDOUT, PHP_EOL.'::warning title=debug-shutdown::junit='.(is_file($file) ? 'yes' : 'no').' last_error='.str_replace(["\r", "\n"], ' ', json_encode($err)).PHP_EOL);
    if (! is_file($file)) {
        return;
    }
    $xml = simplexml_load_file($file);
    $top = $xml->testsuite;
    fwrite(STDOUT, '::warning title=debug-totals::tests='.$top['tests'].' failures='.$top['failures'].' errors='.$top['errors'].' skipped='.$top['skipped'].PHP_EOL);
    $n = 0;
    foreach ($xml->xpath('//testcase[failure or error]') ?: [] as $case) {
        $node = $case->error ?? $case->failure;
        $msg = str_replace(['%', "\r", "\n"], ['%25', ' ', ' | '], substr((string) $node, 0, 1200));
        fwrite(STDOUT, '::warning title=debug-test-'.$n.'::'.$case['file'].' '.$case['name'].' => '.$msg.PHP_EOL);
        if (++$n >= 8) {
            break;
        }
    }
});
