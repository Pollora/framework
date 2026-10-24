<?php

declare(strict_types=1);

// Load Patchwork BEFORE anything else so Brain Monkey can redefine functions
require_once __DIR__.'/../vendor/antecedent/patchwork/Patchwork.php';

// Load test helpers (classes, utility functions)
require_once __DIR__.'/Unit/helpers.php';

require_once __DIR__.'/../vendor/autoload.php';

// TEMPORARY DEBUG (to be reverted)
if (getenv('GITHUB_ACTIONS') && str_contains((string) ($_SERVER['argv'][0] ?? ''), 'pest') && ! in_array('--coverage', $_SERVER['argv'], true)) {
    register_shutdown_function(function (): void {
        $root = __DIR__.'/../build/';
        $out = [];
        if (is_file($root.'debug-junit.xml') && filesize($root.'debug-junit.xml') > 0) {
            $xml = @simplexml_load_file($root.'debug-junit.xml');
            foreach ($xml ? ($xml->xpath('//testcase[failure or error]') ?: []) : [] as $case) {
                $node = $case->error ?? $case->failure;
                $out[] = 'FAIL '.$case['file'].' '.$case['name'].' => '.substr((string) $node, 0, 400);
            }
        }
        if (is_file($root.'debug-coverage.txt')) {
            $lines = file($root.'debug-coverage.txt', FILE_IGNORE_NEW_LINES);
            $class = '';
            foreach ($lines as $line) {
                if (preg_match('/^\S/', $line)) {
                    $class = trim($line);
                } elseif (preg_match('/Lines:\s+([0-9.]+)%/', $line, $m) && (float) $m[1] < 100) {
                    $out[] = 'COV '.$class.' '.trim($line);
                }
                if (str_starts_with(trim($line), 'Lines:') && $class === '') {
                    $out[] = 'SUMMARY '.trim($line);
                }
            }
        }
        foreach (array_slice($out, 0, 40) as $i => $line) {
            fwrite(STDOUT, PHP_EOL.'::warning title=dbg'.$i.'::'.str_replace(['%', "\r", "\n"], ['%25', ' ', ' | '], $line).PHP_EOL);
        }
    });
}
