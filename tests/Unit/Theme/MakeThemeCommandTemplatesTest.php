<?php

declare(strict_types=1);

use Pollora\Theme\UI\Console\MakeThemeCommand;

describe('MakeThemeCommand templates', function (): void {
    $constant = fn (string $name): array => (new ReflectionClassConstant(MakeThemeCommand::class, $name))->getValue();

    it('offers every built-in template in the prompt, and nothing that has no repository', function () use ($constant): void {
        expect(array_keys($constant('TEMPLATE_LABELS')))->toBe(array_keys($constant('TEMPLATES')));
    });

    it('offers Buzz as the magazine template', function () use ($constant): void {
        expect($constant('TEMPLATES'))->toMatchArray([
            'default' => 'pollora/theme-default',
            'ecommerce' => 'pollora/theme-apiary',
            'magazine' => 'pollora/theme-buzz',
        ]);
    });
});
