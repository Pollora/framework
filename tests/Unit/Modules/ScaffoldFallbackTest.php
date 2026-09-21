<?php

declare(strict_types=1);

use Pollora\Plugin\UI\Console\MakePluginCommand;
use Pollora\Theme\UI\Console\BaseThemeCommand;

/**
 * What happens when the starter cannot be downloaded.
 *
 * `make:theme` and `make:plugin` fetch their template from GitHub, and both
 * answered a failure by copying a bundled one from src/Theme/stubs/ or
 * src/Plugin/stubs/. Neither directory exists. realpath() therefore returned
 * false into a `string` return type, so "GitHub did not answer" surfaced as
 * `getTemplatePath(): Return value must be of type string, false returned` —
 * measured on CI the first time a download timed out.
 *
 * The fallback is still a fallback to nothing; what changed is that it says so.
 */
describe('scaffolding fallback', function (): void {
    it('has no bundled theme template to fall back on', function (): void {
        // The premise. If a stub directory is ever shipped, this fails and the
        // fallback becomes real code that needs real tests.
        expect(is_dir(dirname((new ReflectionClass(BaseThemeCommand::class))->getFileName(), 3).'/stubs'))
            ->toBeFalse();
    });

    it('has no bundled plugin template to fall back on', function (): void {
        expect(is_dir(dirname((new ReflectionClass(MakePluginCommand::class))->getFileName(), 3).'/stubs'))
            ->toBeFalse();
    });

    it('returns null for a missing theme stub instead of false', function (): void {
        $method = new ReflectionMethod(BaseThemeCommand::class, 'getTemplatePath');

        expect((string) $method->getReturnType())->toBe('?string');
    });

    it('returns null for a missing plugin stub instead of false', function (): void {
        $method = new ReflectionMethod(MakePluginCommand::class, 'getTemplatePath');

        expect((string) $method->getReturnType())->toBe('?string');
    });
});
