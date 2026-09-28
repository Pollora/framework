<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Block\Infrastructure\Services\BlockRegistrar;
use Pollora\Block\Infrastructure\Services\EditorRuntime;

describe('EditorRuntime', function (): void {
    it('registers the runtime as a handle without a URL, printed inline', function (): void {
        $registered = [];
        $inline = [];

        Functions\when('wp_script_is')->justReturn(false);
        Functions\when('wp_register_script')->alias(function (string $handle, $src, array $deps) use (&$registered): bool {
            $registered[$handle] = ['src' => $src, 'deps' => $deps];

            return true;
        });
        Functions\when('wp_add_inline_script')->alias(function (string $handle, string $script) use (&$inline): bool {
            $inline[$handle] = $script;

            return true;
        });

        (new EditorRuntime)->register();

        // Nothing under vendor/ has a public URL: the script travels inline
        expect($registered[BlockRegistrar::EDITOR_RUNTIME_HANDLE]['src'])->toBeFalse()
            ->and($registered[BlockRegistrar::EDITOR_RUNTIME_HANDLE]['deps'])->toContain('wp-block-editor', 'wp-api-fetch', 'wp-data')
            ->and($inline[BlockRegistrar::EDITOR_RUNTIME_HANDLE])->toContain('root.pollora.blocks = {')
            ->and($inline[BlockRegistrar::EDITOR_RUNTIME_HANDLE])->toContain('bladeEdit: bladeEdit');
    });

    it('registers it once', function (): void {
        Functions\when('wp_script_is')->justReturn(true);
        Functions\expect('wp_register_script')->never();

        (new EditorRuntime)->register();
    });

    it('registers nothing when the script is missing', function (): void {
        Functions\when('wp_script_is')->justReturn(false);
        Functions\expect('wp_register_script')->never();

        (new EditorRuntime('/nonexistent/block-editor.js'))->register();
    });
});
