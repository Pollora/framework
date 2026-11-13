<?php

declare(strict_types=1);

use Pollora\Theme\Domain\Exceptions\ThemeException;
use Pollora\Theme\Domain\Models\ThemeModule;

describe('ThemeModule', function (): void {
    it('starts disabled and toggles with enable, disable and setEnabled', function (): void {
        $theme = new ThemeModule('Demo', '/x');

        expect($theme->isEnabled())->toBeFalse()->and($theme->isDisabled())->toBeTrue();

        $theme->enable();
        expect($theme->isEnabled())->toBeTrue()->and($theme->isDisabled())->toBeFalse();

        $theme->disable();
        expect($theme->isEnabled())->toBeFalse();

        expect($theme->setEnabled(true))->toBe($theme)->and($theme->isEnabled())->toBeTrue();
    });

    it('maps headers to metadata with defaults for the missing ones', function (): void {
        $theme = (new ThemeModule('Demo', '/x'))->setHeaders(['Version' => '2.0', 'Author' => 'Ann', 'ThemeURI' => 'https://t.test']);

        expect($theme->getVersion())->toBe('2.0')
            ->and($theme->getAuthor())->toBe('Ann')
            ->and($theme->getThemeUri())->toBe('https://t.test')
            ->and($theme->getAuthorUri())->toBeNull()
            ->and($theme->getHeaders())->toBe(['Version' => '2.0', 'Author' => 'Ann', 'ThemeURI' => 'https://t.test']);

        $bare = new ThemeModule('Bare', '/x');
        expect($bare->getVersion())->toBe('1.0.0')->and($bare->getAuthor())->toBe('');
    });

    it('treats an empty or zero Template as no parent theme', function (): void {
        $theme = new ThemeModule('Child', '/x');

        expect($theme->isChildTheme())->toBeFalse();

        foreach (['', '0'] as $value) {
            $theme->set('Template', $value);
            expect($theme->isChildTheme())->toBeFalse();
        }

        $theme->set('Template', 'parent');
        expect($theme->isChildTheme())->toBeTrue()
            ->and($theme->getParentTheme())->toBe('parent')
            ->and($theme->getTemplate())->toBe('parent');
    });

    it('uses its own lowercase name as stylesheet and template without a parent', function (): void {
        $theme = new ThemeModule('MyTheme', '/x');

        expect($theme->getStylesheet())->toBe('mytheme')->and($theme->getTemplate())->toBe('mytheme');
    });

    it('exposes the WordPress-style theme data', function (): void {
        $theme = (new ThemeModule('Demo', '/x'))->setHeaders(['Version' => '4', 'Template' => 'base']);

        expect($theme->getThemeData())->toMatchArray([
            'Name' => 'Demo',
            'Version' => '4',
            'Template' => 'base',
            'Stylesheet' => 'demo',
        ]);
    });

    it('returns the screenshot path only when the file exists', function (): void {
        $dir = sys_get_temp_dir().'/pollora-shot-'.bin2hex(random_bytes(6));
        mkdir($dir);

        try {
            $theme = new ThemeModule('Demo', $dir);
            $before = $theme->getScreenshot();
            file_put_contents($dir.'/screenshot.png', 'x');
            $after = $theme->getScreenshot();
        } finally {
            @unlink($dir.'/screenshot.png');
            @rmdir($dir);
        }

        expect($before)->toBeNull()->and($after)->toBe($dir.'/screenshot.png');
    });

    it('builds a PSR-4 namespace from the lowercase theme name', function (): void {
        expect((new ThemeModule('my-theme_x', '/x'))->getNamespace())->toBe('Theme\\MyThemeX')
            ->and((new ThemeModule('a', '/x'))->getRootNamespace())->toBe('Theme');
    });

    it('does nothing on register and boot', function (): void {
        $theme = new ThemeModule('Demo', '/x');
        $theme->register();
        $theme->boot();

        expect($theme->isEnabled())->toBeFalse();
    });
});

describe('ThemeException', function (): void {
    it('names the missing theme', function (): void {
        expect(ThemeException::notFound('foo')->getMessage())->toBe("Theme 'foo' not found.");
    });

    it('lists the missing files', function (): void {
        expect(ThemeException::missingRequiredFiles('foo', ['a', 'b'])->getMessage())
            ->toBe("Theme 'foo' is missing required files: a, b");
    });
});
