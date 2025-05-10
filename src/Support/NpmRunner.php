<?php

declare(strict_types=1);

namespace Pollora\Support;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Utility class to run npm commands in a given working directory.
 */
class NpmRunner
{
    protected string $workingDirectory;

    /**
     * Instantiate NpmRunner with the specified working directory.
     *
     * @param  string  $workingDirectory  Path to the directory where npm commands will be executed.
     */
    public function __construct(string $workingDirectory)
    {
        $this->workingDirectory = $workingDirectory;
    }

    /**
     * Run `npm install` in the working directory.
     *
     * @return $this
     */
    public function install(): self
    {
        $this->runCommand(['npm', 'install']);

        return $this;
    }

    /**
     * Run `npm run build` in the working directory.
     *
     * @return $this
     */
    public function build(): self
    {
        $this->runCommand(['npm', 'run', 'build']);

        return $this;
    }

    /**
     * Run a shell command in the working directory.
     *
     * @param  array<int, string>  $command  The command to execute.
     *
     * @throws ProcessFailedException If the command fails.
     */
    protected function runCommand(array $command): void
    {
        $process = new Process($command, $this->workingDirectory);
        $process->setTimeout(300); // 5 minutes by precaution
        $process->run(function ($type, $buffer) {
            echo $buffer; // or use a logger here if needed
        });

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
