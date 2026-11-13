<?php

declare(strict_types=1);

use Pollora\View\Domain\Models\ResolvedTemplate;

describe('ResolvedTemplate', function (): void {
    it('tells a Blade template from a PHP one by its extension', function (): void {
        $blade = ResolvedTemplate::fromPath('single.blade.php', '/t/single.blade.php');
        $php = ResolvedTemplate::fromPath('single.php', '/t/single.php');

        expect($blade->isBlade())->toBeTrue()
            ->and($blade->isPhp())->toBeFalse()
            ->and($php->isBlade())->toBeFalse()
            ->and($php->isPhp())->toBeTrue();
    });

    it('exposes the name and the file path it was built with', function (): void {
        $template = new ResolvedTemplate('page.blade.php', '/themes/x/page.blade.php', true);

        expect($template->getTemplateName())->toBe('page.blade.php')
            ->and($template->getFilePath())->toBe('/themes/x/page.blade.php');
    });

    it('gives a view name for Blade templates only', function (): void {
        expect((new ResolvedTemplate('partials.card.blade.php', '/p', true))->getViewName())->toBe('partials.card')
            ->and((new ResolvedTemplate('card.php', '/p', false))->getViewName())->toBeNull();
    });

    it('strips the matching extension to get the base name', function (): void {
        expect((new ResolvedTemplate('archive.blade.php', '/p', true))->getBaseTemplateName())->toBe('archive')
            ->and((new ResolvedTemplate('archive.php', '/p', false))->getBaseTemplateName())->toBe('archive');
    });

    it('describes itself as an array', function (): void {
        expect(ResolvedTemplate::fromPath('home.blade.php', '/t/home.blade.php')->toArray())->toBe([
            'template_name' => 'home.blade.php',
            'file_path' => '/t/home.blade.php',
            'is_blade' => true,
            'view_name' => 'home',
            'base_name' => 'home',
        ])->and(ResolvedTemplate::fromPath('home.php', '/t/home.php')->toArray())->toBe([
            'template_name' => 'home.php',
            'file_path' => '/t/home.php',
            'is_blade' => false,
            'view_name' => null,
            'base_name' => 'home',
        ]);
    });

    it('rejects an empty template name or file path', function (string $name, string $path, string $message): void {
        new ResolvedTemplate($name, $path, false);
    })->with([
        'empty name' => ['', '/p', 'Template name cannot be empty'],
        'zero name' => ['0', '/p', 'Template name cannot be empty'],
        'empty path' => ['a.php', '', 'File path cannot be empty'],
        'zero path' => ['a.php', '0', 'File path cannot be empty'],
    ])->throws(InvalidArgumentException::class);

    it('names the field that is empty in the exception message', function (): void {
        expect(fn (): ResolvedTemplate => new ResolvedTemplate('', '/p', false))->toThrow(InvalidArgumentException::class, 'Template name cannot be empty')
            ->and(fn (): ResolvedTemplate => new ResolvedTemplate('a.php', '', false))->toThrow(InvalidArgumentException::class, 'File path cannot be empty');
    });
});
