<?php

declare(strict_types=1);

use Pollora\Translation\Infrastructure\Services\GettextMoCompiler;

/**
 * Read a .mo file back into an original => translation map.
 *
 * @return array<string, string>
 */
function covReadMoFile(string $path): array
{
    $data = file_get_contents($path);
    $header = unpack('V7', substr($data, 0, 28));
    $count = $header[3];
    $origTable = $header[4];
    $transTable = $header[5];
    $result = [];

    for ($i = 0; $i < $count; $i++) {
        $o = unpack('Vlen/Voff', substr($data, $origTable + $i * 8, 8));
        $t = unpack('Vlen/Voff', substr($data, $transTable + $i * 8, 8));
        $result[substr($data, $o['off'], $o['len'])] = substr($data, $t['off'], $t['len']);
    }

    return ['__magic' => dechex($header[1])] + $result;
}

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/pollora-mo-'.uniqid();
    mkdir($this->dir);
    $this->compiler = new GettextMoCompiler;
});

afterEach(function () {
    foreach (glob($this->dir.'/*') ?: [] as $f) {
        unlink($f);
    }
    rmdir($this->dir);
});

describe('GettextMoCompiler', function () {
    it('returns false when the source file does not exist', function () {
        expect($this->compiler->compile($this->dir.'/missing.po'))->toBeFalse();
    });

    it('returns false and writes nothing when the po file has no entries', function () {
        file_put_contents($this->dir.'/empty.po', "# just a comment\n");

        expect($this->compiler->compile($this->dir.'/empty.po'))->toBeFalse()
            ->and(file_exists($this->dir.'/empty.mo'))->toBeFalse();
    });

    it('compiles a po file into a mo file next to it with the gettext magic number', function () {
        file_put_contents($this->dir.'/fr.po', "msgid \"Hello\"\nmsgstr \"Bonjour\"\n");

        expect($this->compiler->compile($this->dir.'/fr.po'))->toBeTrue();

        $mo = covReadMoFile($this->dir.'/fr.mo');
        expect($mo['__magic'])->toBe('950412de')
            ->and($mo['Hello'])->toBe('Bonjour');
    });

    it('writes to the requested output file', function () {
        file_put_contents($this->dir.'/fr.po', "msgid \"Yes\"\nmsgstr \"Oui\"\n");

        expect($this->compiler->compile($this->dir.'/fr.po', $this->dir.'/custom.mo'))->toBeTrue()
            ->and(file_exists($this->dir.'/custom.mo'))->toBeTrue()
            ->and(file_exists($this->dir.'/fr.mo'))->toBeFalse();
    });

    it('joins multi-line strings, unescapes sequences and qualifies contexts with EOT', function () {
        $po = <<<'PO'
# comment
msgid ""
msgstr ""
"Project-Id-Version: x\n"

msgctxt "menu"
msgid "Open"
msgstr "Ouvrir"

msgid "Line one "
"line two"
msgstr "Ligne un\n"
"\"deux\"\t\\fin"

PO;
        file_put_contents($this->dir.'/a.po', $po);

        $this->compiler->compile($this->dir.'/a.po');
        $mo = covReadMoFile($this->dir.'/a.mo');

        expect($mo["menu\x04Open"])->toBe('Ouvrir')
            ->and($mo['Line one line two'])->toBe("Ligne un\n\"deux\"\t\\fin")
            ->and($mo[''])->toBe("Project-Id-Version: x\n");
    });

    it('flushes the last entry even without a trailing blank line', function () {
        file_put_contents($this->dir.'/b.po', "msgid \"A\"\nmsgstr \"B\"");

        $this->compiler->compile($this->dir.'/b.po');

        expect(covReadMoFile($this->dir.'/b.mo')['A'])->toBe('B');
    });

    it('compiles every po file of a directory and counts the successes', function () {
        file_put_contents($this->dir.'/one.po', "msgid \"A\"\nmsgstr \"B\"\n");
        file_put_contents($this->dir.'/two.po', "msgid \"C\"\nmsgstr \"D\"\n");
        file_put_contents($this->dir.'/empty.po', "\n");
        file_put_contents($this->dir.'/ignored.txt', 'x');

        expect($this->compiler->compileDirectory($this->dir))->toBe(2)
            ->and(file_exists($this->dir.'/one.mo'))->toBeTrue()
            ->and(file_exists($this->dir.'/two.mo'))->toBeTrue()
            ->and(file_exists($this->dir.'/empty.mo'))->toBeFalse();
    });

    it('returns zero for a directory that does not exist', function () {
        expect($this->compiler->compileDirectory($this->dir.'/nope'))->toBe(0);
    });
});
