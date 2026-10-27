<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Dashboard\Infrastructure\Adapters\WordPressEnvironment;

/**
 * Kept out of the unit suite: once Brain Monkey has defined wp_get_theme(),
 * it stays defined for the rest of the process, and some unit tests rely on
 * it being undefined.
 */
it('reads the active theme from wp_get_theme()', function (): void {
    Functions\when('wp_get_theme')->justReturn(new class
    {
        public function get(string $header): string
        {
            return ['Name' => 'Apiary', 'Version' => '1.2.0'][$header] ?? '';
        }

        public function get_template(): string
        {
            return 'apiary';
        }
    });

    expect((new WordPressEnvironment)->activeTheme())
        ->toBe(['name' => 'Apiary', 'version' => '1.2.0', 'template' => 'apiary']);
});
