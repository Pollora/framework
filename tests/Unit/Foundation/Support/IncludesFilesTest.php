<?php

declare(strict_types=1);

use Pollora\Foundation\Support\IncludesFiles;

describe('IncludesFiles', function (): void {
    beforeEach(function (): void {
        $this->includer = new class
        {
            use IncludesFiles {
                includes as public;
            }
        };

        // Create temporary directory with test files
        $this->tempDir = sys_get_temp_dir().'/pollora_test_includes_'.uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function (): void {
        // Clean up temp files
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir.'/*'));
            rmdir($this->tempDir);
        }
    });

    it('includes PHP files from a directory', function (): void {
        file_put_contents($this->tempDir.'/file1.php', '<?php $GLOBALS["included_file1"] = true;');
        file_put_contents($this->tempDir.'/file2.php', '<?php $GLOBALS["included_file2"] = true;');

        $this->includer->includes($this->tempDir);

        expect($GLOBALS['included_file1'] ?? false)->toBeTrue();
        expect($GLOBALS['included_file2'] ?? false)->toBeTrue();

        unset($GLOBALS['included_file1'], $GLOBALS['included_file2']);
    });

    it('only includes files matching the pattern', function (): void {
        file_put_contents($this->tempDir.'/config.php', '<?php $GLOBALS["config_loaded"] = true;');
        file_put_contents($this->tempDir.'/readme.txt', 'not php');

        $this->includer->includes($this->tempDir, '*.php');

        expect($GLOBALS['config_loaded'] ?? false)->toBeTrue();

        unset($GLOBALS['config_loaded']);
    });

    it('accepts custom file pattern', function (): void {
        file_put_contents($this->tempDir.'/app.config.php', '<?php $GLOBALS["custom_pattern"] = true;');
        file_put_contents($this->tempDir.'/other.php', '<?php $GLOBALS["other_pattern"] = true;');

        $this->includer->includes($this->tempDir, '*.config.php');

        expect($GLOBALS['custom_pattern'] ?? false)->toBeTrue();
        expect($GLOBALS['other_pattern'] ?? false)->toBeFalse();

        unset($GLOBALS['custom_pattern']);
    });

    it('accepts array of paths', function (): void {
        $secondDir = $this->tempDir.'_second';
        mkdir($secondDir, 0755, true);

        file_put_contents($this->tempDir.'/first.php', '<?php $GLOBALS["from_first_dir"] = true;');
        file_put_contents($secondDir.'/second.php', '<?php $GLOBALS["from_second_dir"] = true;');

        $this->includer->includes([$this->tempDir, $secondDir]);

        expect($GLOBALS['from_first_dir'] ?? false)->toBeTrue();
        expect($GLOBALS['from_second_dir'] ?? false)->toBeTrue();

        unset($GLOBALS['from_first_dir'], $GLOBALS['from_second_dir']);
        array_map('unlink', glob($secondDir.'/*'));
        rmdir($secondDir);
    });
});
