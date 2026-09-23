<?php

declare(strict_types=1);

use Pollora\Foundation\Console\Commands\Concerns\ResolvesSourceDirectory;

describe('ResolvesSourceDirectory', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir().'/pollora-source-dir-'.uniqid();
        mkdir($this->root, 0755, true);

        $this->resolver = new class
        {
            use ResolvesSourceDirectory {
                resolveSourceDirectory as public;
            }
        };
    });

    afterEach(function (): void {
        foreach (['app', 'src'] as $directory) {
            if (is_dir($this->root.'/'.$directory)) {
                rmdir($this->root.'/'.$directory);
            }
        }

        rmdir($this->root);
    });

    it('prefers app/ when it exists', function (): void {
        mkdir($this->root.'/app');

        expect($this->resolver->resolveSourceDirectory($this->root))->toBe($this->root.'/app');
    });

    it('uses src/ when that is the only one there', function (): void {
        // The regression this pins. The autoloaders map a namespace onto
        // app/ when it exists and onto src/ otherwise, one directory only. A
        // generator that wrote into app/ regardless created it on a src/
        // target, which flipped the autoloader and discovery over to app/ —
        // and every class already in src/ stopped being loaded.
        mkdir($this->root.'/src');

        expect($this->resolver->resolveSourceDirectory($this->root))->toBe($this->root.'/src');
    });

    it('prefers app/ when both exist, as the autoloaders do', function (): void {
        mkdir($this->root.'/app');
        mkdir($this->root.'/src');

        expect($this->resolver->resolveSourceDirectory($this->root))->toBe($this->root.'/app');
    });

    it('falls back to app/ for a target with neither', function (): void {
        expect($this->resolver->resolveSourceDirectory($this->root))->toBe($this->root.'/app');
    });

    it('ignores a trailing slash on the root', function (): void {
        mkdir($this->root.'/src');

        expect($this->resolver->resolveSourceDirectory($this->root.'/'))->toBe($this->root.'/src');
    });
});
