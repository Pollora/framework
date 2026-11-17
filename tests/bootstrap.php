<?php

declare(strict_types=1);
use Pest\Mutate\Support\StreamWrapper;

/*
 * Mutation testing (`pest --mutate`) and Patchwork both rewrite what PHP loads, through a stream wrapper on `file`.
 * Pest starts its wrapper in each process that runs the tests of one mutant, before this file; Patchwork, loaded
 * below, replaces it and reads the original source, so the mutant is never run and every test passes (the mutant
 * "survives"), or, when the two wrappers meet on a directory function, the process dies and the mutant counts as
 * killed. Either way the score means nothing.
 *
 * In such a process only, this file therefore gives `file` back to Patchwork and loads the mutated source itself,
 * after Patchwork, so that Brain Monkey can still redefine functions in it. A mutated source that PHP has already
 * included (a file listed in `autoload.files`) cannot be replaced: its mutants survive, which the score shows.
 */
$mutatedSource = getenv('PEST_MUTATION_TESTING');
$mutating = is_string($mutatedSource) && class_exists(StreamWrapper::class);

if ($mutating) {
    StreamWrapper::disable();
}

// Load Patchwork BEFORE anything else so Brain Monkey can redefine functions
require_once __DIR__.'/../vendor/antecedent/patchwork/Patchwork.php';

// Load test helpers (classes, utility functions)
require_once __DIR__.'/Unit/helpers.php';

require_once __DIR__.'/../vendor/autoload.php';

if ($mutating) {
    /**
     * Loads the mutated version of the source file under test, in place of the original.
     *
     * The mutant is written next to the original, under another name, so that `__DIR__` (read by the service
     * providers to find their config and views) still points at the real directory; `__FILE__` is replaced by the
     * original path in the copy. PHP then includes it, through Patchwork.
     *
     * @param  string  $original  The source file the mutant replaces (`PEST_MUTATION_TESTING`).
     * @param  string  $mutant  The mutated source Pest wrote (`PEST_MUTATION_FILE`).
     */
    $loadMutant = static function (string $original, string $mutant): void {
        $realOriginal = realpath($original);

        if ($realOriginal === false || in_array($realOriginal, array_map('realpath', get_included_files()), true)) {
            return;
        }

        $source = (string) file_get_contents($mutant);
        $code = '';

        foreach (token_get_all($source) as $token) {
            $code .= is_array($token) ? ($token[0] === T_FILE ? var_export($realOriginal, true) : $token[1]) : $token;
        }

        $copy = dirname($realOriginal).'/.mutant-'.getmypid().'-'.basename($realOriginal);
        file_put_contents($copy, $code);
        register_shutdown_function(static function () use ($copy): void {
            @unlink($copy);
        });

        require $copy;
    };

    $loadMutant($mutatedSource, (string) getenv('PEST_MUTATION_FILE'));
}
