<?php

declare(strict_types=1);

use Pollora\Theme\Infrastructure\Services\EditorStyleResolver;

/**
 * Which stylesheets the block editor should be handed.
 *
 * A theme declaring `editor-styles` support wants its own rules in the editor,
 * but that support loads nothing on its own — something has to name the files.
 * These cover the reading of the Vite manifest that names them.
 */
function manifestIn(string $dir, array $manifest): EditorStyleResolver
{
    $path = $dir.'/build/theme/acme';
    mkdir($path, 0777, true);
    file_put_contents($path.'/manifest.json', json_encode($manifest));

    return new EditorStyleResolver($dir);
}

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/editor-styles-'.bin2hex(random_bytes(6));
    mkdir($this->dir, 0777, true);
});

afterEach(function (): void {
    exec('rm -rf '.escapeshellarg($this->dir));
});

describe('EditorStyleResolver', function (): void {
    it('returns nothing when the theme has no build yet', function (): void {
        expect((new EditorStyleResolver($this->dir))->resolve('acme'))->toBe([]);
    });

    it('takes the stylesheets an entry point pulls in', function (): void {
        $resolver = manifestIn($this->dir, [
            'resources/assets/app.js' => [
                'file' => 'assets/app-abc.js',
                'isEntry' => true,
                'css' => ['assets/app-def.css'],
            ],
        ]);

        expect($resolver->resolve('acme'))->toBe(['build/theme/acme/assets/app-def.css']);
    });

    it('takes a pure CSS entry, which carries no css key of its own', function (): void {
        $resolver = manifestIn($this->dir, [
            'resources/views/blocks/hero/editor.css' => [
                'file' => 'assets/editor-abc.css',
                'isEntry' => true,
            ],
        ]);

        expect($resolver->resolve('acme'))->toBe(['build/theme/acme/assets/editor-abc.css']);
    });

    it('leaves shared chunks alone, since their entry already pulls them in', function (): void {
        $resolver = manifestIn($this->dir, [
            'resources/assets/app.js' => [
                'file' => 'assets/app-abc.js',
                'isEntry' => true,
                'css' => ['assets/shared-xyz.css'],
            ],
            '_shared-xyz.js' => [
                'file' => 'assets/shared-xyz.js',
                'css' => ['assets/shared-xyz.css'],
            ],
        ]);

        // Listed twice in the manifest, injected once.
        expect($resolver->resolve('acme'))->toBe(['build/theme/acme/assets/shared-xyz.css']);
    });

    it('survives a manifest that is not valid JSON', function (): void {
        $path = $this->dir.'/build/theme/acme';
        mkdir($path, 0777, true);
        file_put_contents($path.'/manifest.json', '{ this is not json');

        expect((new EditorStyleResolver($this->dir))->resolve('acme'))->toBe([]);
    });
});
