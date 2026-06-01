<?php

declare(strict_types=1);

namespace Pollora\Translation\Domain\Contracts;

/**
 * Contract for compiling translation source files into binary format.
 *
 * Implementations handle the conversion of human-readable translation files
 * (e.g. GNU gettext .po) into their optimized binary counterparts (e.g. .mo)
 * used at runtime by WordPress and other gettext consumers.
 */
interface TranslationCompilerInterface
{
    /**
     * Compile a translation source file into its binary equivalent.
     *
     * @param  string  $sourceFile  Absolute path to the source translation file.
     * @param  string|null  $outputFile  Absolute path for the compiled output. When null,
     *                                   the implementation should derive the path from
     *                                   the source (e.g. replace .po with .mo).
     * @return bool True when the compilation succeeds, false otherwise.
     */
    public function compile(string $sourceFile, ?string $outputFile = null): bool;

    /**
     * Compile every translation source file found in a directory.
     *
     * @param  string  $directory  Absolute path to the directory to scan.
     * @return int Number of files successfully compiled.
     */
    public function compileDirectory(string $directory): int;
}