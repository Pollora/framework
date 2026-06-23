<?php

declare(strict_types=1);

namespace Pollora\Translation\Infrastructure\Services;

use Pollora\Translation\Domain\Contracts\TranslationCompilerInterface;

/**
 * Compile GNU gettext .po files into .mo binary format.
 *
 * This is a pure-PHP implementation that does not require the `msgfmt`
 * system tool, making it portable across all environments (Docker, CI,
 * shared hosting, etc.).
 *
 * The generated .mo files follow the GNU gettext specification:
 *
 * @see https://www.gnu.org/software/gettext/manual/html_node/MO-Files.html
 */
class GettextMoCompiler implements TranslationCompilerInterface
{
    /**
     * {@inheritDoc}
     */
    public function compile(string $sourceFile, ?string $outputFile = null): bool
    {
        if (! file_exists($sourceFile)) {
            return false;
        }

        $outputFile ??= substr($sourceFile, 0, -3).'.mo';
        $entries = $this->parsePoFile($sourceFile);

        if ($entries === []) {
            return false;
        }

        return $this->writeMoFile($outputFile, $entries);
    }

    /**
     * {@inheritDoc}
     */
    public function compileDirectory(string $directory): int
    {
        if (! is_dir($directory)) {
            return 0;
        }

        $compiled = 0;
        $poFiles = glob($directory.'/*.po');

        foreach ($poFiles as $poFile) {
            if ($this->compile($poFile)) {
                $compiled++;
            }
        }

        return $compiled;
    }

    /**
     * Parse a .po file and extract msgid/msgstr pairs.
     *
     * Handles multi-line strings, msgctxt context markers (joined with
     * the EOT separator \x04 as per gettext convention), and standard
     * PO escape sequences.
     *
     * @param  string  $poFile  Absolute path to the .po file.
     * @return array<string, string> Associative array of original => translation.
     */
    private function parsePoFile(string $poFile): array
    {
        $content = file_get_contents($poFile);

        if ($content === false) {
            return [];
        }

        $entries = [];
        $context = '';
        $msgid = '';
        $msgstr = '';
        $activeField = null;

        $lines = explode("\n", $content);
        $lines[] = ''; // Ensure the last entry is flushed

        foreach ($lines as $line) {
            $line = rtrim($line);

            if ($line === '' || $line[0] === '#') {
                if ($line === '' && $activeField !== null) {
                    $this->storeEntry($entries, $context, $msgid, $msgstr);
                    $context = '';
                    $msgid = '';
                    $msgstr = '';
                    $activeField = null;
                }

                continue;
            }

            if (str_starts_with($line, 'msgctxt ')) {
                $context = $this->extractQuoted($line, 8);
                $activeField = 'msgctxt';
            } elseif (str_starts_with($line, 'msgid ')) {
                $msgid = $this->extractQuoted($line, 6);
                $activeField = 'msgid';
            } elseif (str_starts_with($line, 'msgstr ')) {
                $msgstr = $this->extractQuoted($line, 7);
                $activeField = 'msgstr';
            } elseif ($line[0] === '"' && $activeField !== null) {
                $continued = $this->extractQuoted($line, 0);
                match ($activeField) {
                    'msgctxt' => $context .= $continued,
                    'msgid' => $msgid .= $continued,
                    'msgstr' => $msgstr .= $continued,
                };
            }
        }

        return $entries;
    }

    /**
     * Store a parsed PO entry into the entries array.
     *
     * When a msgctxt is present, the key is built as "context\x04msgid"
     * following the gettext convention for context-qualified lookups.
     *
     * @param  array<string, string>  $entries  Accumulated entries (modified by reference).
     * @param  string  $context  Optional msgctxt value.
     * @param  string  $msgid  The original string.
     * @param  string  $msgstr  The translated string.
     */
    private function storeEntry(array &$entries, string $context, string $msgid, string $msgstr): void
    {
        $key = $context !== '' ? $context."\x04".$msgid : $msgid;
        $entries[$key] = $msgstr;
    }

    /**
     * Extract a string value from a quoted PO line.
     *
     * Strips surrounding double-quotes and resolves standard PO escape
     * sequences (\n, \t, \", \\).
     *
     * @param  string  $line  The raw PO line.
     * @param  int  $offset  Character offset where the quoted value starts.
     * @return string The unescaped string value.
     */
    private function extractQuoted(string $line, int $offset): string
    {
        $quoted = trim(substr($line, $offset));

        if (strlen($quoted) >= 2 && $quoted[0] === '"' && str_ends_with($quoted, '"')) {
            $quoted = substr($quoted, 1, -1);
        }

        return str_replace(
            ['\\n', '\\t', '\\"', '\\\\'],
            ["\n", "\t", '"', '\\'],
            $quoted
        );
    }

    /**
     * Write entries to a .mo binary file.
     *
     * The .mo layout follows the GNU specification:
     *   - 28-byte header (magic, revision, counts, offsets)
     *   - Original strings descriptor table (length + offset pairs)
     *   - Translation strings descriptor table (length + offset pairs)
     *   - Concatenated NUL-terminated string data
     *
     * Entries are sorted by original key to enable binary search lookups
     * as required by the specification.
     *
     * @param  string  $outputFile  Absolute path for the output .mo file.
     * @param  array<string, string>  $entries  Sorted msgid => msgstr pairs.
     * @return bool True when the file was written successfully.
     */
    private function writeMoFile(string $outputFile, array $entries): bool
    {
        ksort($entries);

        $count = count($entries);
        $headerSize = 28;
        $tableEntrySize = 8; // 4 bytes length + 4 bytes offset
        $originalsTableOffset = $headerSize;
        $translationsTableOffset = $headerSize + ($count * $tableEntrySize);
        $stringsStartOffset = $headerSize + ($count * $tableEntrySize * 2);

        $originals = array_keys($entries);
        $translations = array_values($entries);

        // Build descriptor tables and string data
        $originalDescriptors = '';
        $translationDescriptors = '';
        $stringData = '';
        $currentOffset = $stringsStartOffset;

        foreach ($originals as $original) {
            $length = strlen($original);
            $originalDescriptors .= pack('V', $length).pack('V', $currentOffset);
            $stringData .= $original."\0";
            $currentOffset += $length + 1;
        }

        foreach ($translations as $translation) {
            $length = strlen($translation);
            $translationDescriptors .= pack('V', $length).pack('V', $currentOffset);
            $stringData .= $translation."\0";
            $currentOffset += $length + 1;
        }

        // Assemble the file: header + tables + strings
        $header = pack(
            'V7',
            0x950412DE,               // Magic number (little-endian)
            0,                         // Revision
            $count,                    // Number of strings
            $originalsTableOffset,     // Offset of originals table
            $translationsTableOffset,  // Offset of translations table
            0,                         // Hash table size (unused)
            0                          // Hash table offset (unused)
        );

        return file_put_contents(
            $outputFile,
            $header.$originalDescriptors.$translationDescriptors.$stringData
        ) !== false;
    }
}
