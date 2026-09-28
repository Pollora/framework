<?php

declare(strict_types=1);

namespace Pollora\Block\Infrastructure\Services;

/**
 * The framework's editor script for blocks rendered on the server.
 *
 * resources/js/block-editor.js defines window.pollora.blocks, which the edit
 * and save of every block made by pollora:make:block call. It is registered
 * as a handle without a URL — nothing under vendor/ has a public one — and
 * printed as that handle's inline script, wherever a block's editor script
 * depends on it.
 */
final readonly class EditorRuntime
{
    /**
     * WordPress scripts the runtime uses.
     */
    private const array DEPENDENCIES = ['wp-api-fetch', 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-data', 'wp-element'];

    public function __construct(
        private string $scriptPath = __DIR__.'/../../../../resources/js/block-editor.js',
    ) {}

    /**
     * Register the handle and its inline script. Hooked on `init`.
     */
    public function register(): void
    {
        if (wp_script_is(BlockRegistrar::EDITOR_RUNTIME_HANDLE, 'registered')) {
            return;
        }

        $script = is_readable($this->scriptPath) ? file_get_contents($this->scriptPath) : false;

        if ($script === false) {
            return;
        }

        wp_register_script(BlockRegistrar::EDITOR_RUNTIME_HANDLE, false, self::DEPENDENCIES, null, true);
        wp_add_inline_script(BlockRegistrar::EDITOR_RUNTIME_HANDLE, $script);
    }
}
