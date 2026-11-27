<?php

declare(strict_types=1);

use Pest\Mutate\Support\StreamWrapper;
use Symfony\Component\Process\Process;

/**
 * Runs `tests/bootstrap.php` in a new PHP process with the variables Pest gives the process of a mutant, then calls a
 * function whose mutated version returns another value, and reads a directory (which kills the process when Patchwork
 * is stacked on Pest's wrapper).
 *
 * @param  string  $wrapper  PHP run before the bootstrap: what puts, or not, Pest's wrapper on `file`.
 */
function runBootstrapAsMutant(string $wrapper): Process
{
    $directory = sys_get_temp_dir().'/pollora-mutant-'.bin2hex(random_bytes(8));
    $original = $directory.'/Subject.php';
    $mutant = $directory.'/Mutant.php';

    mkdir($directory);
    file_put_contents($original, "<?php function pollora_mutant_subject(): string { return 'original'; }");
    file_put_contents($mutant, "<?php function pollora_mutant_subject(): string { return 'mutated'; }");

    $code = 'require '.var_export(dirname(__DIR__, 2).'/vendor/autoload.php', true).';'
        .$wrapper
        .'require '.var_export(__DIR__.'/../bootstrap.php', true).';'
        .'echo pollora_mutant_subject();'
        .'readdir(opendir('.var_export($directory, true).'));';

    $process = new Process(
        // Deprecations of the dependencies (the lowest ones, on a recent PHP) are not what is looked for
        [PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'error_reporting='.(E_ALL & ~E_DEPRECATED), '-r', $code],
        env: ['PEST_MUTATION_TESTING' => $original, 'PEST_MUTATION_FILE' => $mutant],
    );

    try {
        $process->run();
    } finally {
        // The copy the bootstrap includes is removed by its process: rmdir() fails if it was left behind
        unlink($original);
        unlink($mutant);
        rmdir($directory);
    }

    return $process;
}

it('loads the mutant, without a notice, in a process PHPUnit isolates a test in', function (): void {
    // Such a process inherits the variables, but nothing started Pest's wrapper in it
    $process = runBootstrapAsMutant('');

    expect($process->getErrorOutput())->toBe('')
        ->and($process->getOutput())->toBe('mutated');
});

it('takes `file` back from Pest and loads the mutant in the process Pest starts', function (): void {
    $process = runBootstrapAsMutant(
        StreamWrapper::class.'::start(getenv("PEST_MUTATION_TESTING"), getenv("PEST_MUTATION_FILE"));'
    );

    expect($process->getErrorOutput())->toBe('')
        ->and($process->getOutput())->toBe('mutated');
});
