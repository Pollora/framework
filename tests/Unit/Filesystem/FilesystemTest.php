<?php

declare(strict_types=1);

use Pollora\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->filesystem = new Filesystem;
});

describe('Filesystem', function (): void {
    describe('normalizePath', function (): void {
        it('converts backslashes to forward slashes', function (): void {
            expect($this->filesystem->normalizePath('path\\to\\file'))->toBe('path/to/file');
        });

        it('collapses multiple forward slashes', function (): void {
            expect($this->filesystem->normalizePath('path//to///file'))->toBe('path/to/file');
        });

        it('handles mixed separators', function (): void {
            expect($this->filesystem->normalizePath('path\\\\to//file'))->toBe('path/to/file');
        });

        it('uses custom separator', function (): void {
            expect($this->filesystem->normalizePath('path\\to\\file', '\\'))->toBe('path\\to\\file');
        });

        it('returns empty string as-is', function (): void {
            expect($this->filesystem->normalizePath(''))->toBe('');
        });

        it('normalizes leading slashes', function (): void {
            expect($this->filesystem->normalizePath('//root/path'))->toBe('/root/path');
        });
    });

    describe('getRelativePath', function (): void {
        it('returns empty string for identical paths', function (): void {
            expect($this->filesystem->getRelativePath('/var/www', '/var/www'))
                ->toBe('');
        });

        it('returns relative path from base directory to target file', function (): void {
            // base='/var/www/html/' target='/var/www/html/index.php'
            // sourceDirs after pop: ['var','www','html'], targetFile: 'index.php', targetDirs: ['var','www','html']
            // all match → path = 'index.php'
            expect($this->filesystem->getRelativePath('/var/www/html/', '/var/www/html/index.php'))
                ->toBe('index.php');
        });

        it('traverses up directories with ../', function (): void {
            // base='/var/www/html/page' target='/var/log/file.txt'
            // sourceDirs after pop: ['var','www','html'], targetFile: 'file.txt', targetDirs: ['var','log']
            // i=0: var===var match. i=1: www!==log break. remaining source: ['www','html'], remaining target: ['log']
            // path = '../../log/file.txt'
            expect($this->filesystem->getRelativePath('/var/www/html/page', '/var/log/file.txt'))
                ->toBe('../../log/file.txt');
        });

        it('handles paths with backslashes', function (): void {
            expect($this->filesystem->getRelativePath('C:\\Users\\test\\dir', 'C:\\Users\\test\\file.txt'))
                ->toBe('file.txt');
        });

        it('handles sibling directories', function (): void {
            // base='/a/b/c' target='/a/b/x/y'
            // sourceDirs after pop: ['a','b'], targetFile: 'y', targetDirs: ['a','b','x']
            // all 2 match → path = 'x/y'
            expect($this->filesystem->getRelativePath('/a/b/c', '/a/b/x/y'))
                ->toBe('x/y');
        });
    });
});
