<?php

declare(strict_types=1);

use Pollora\View\Infrastructure\Services\TemplateMarker;

/**
 * The marker exists so a page can say which template answered it.
 *
 * Themes had been doing this by hand, one view at a time, and drifting:
 * theme-apiary marks most of its views and not its front page, so anything
 * relying on the marker measures nothing there. Deriving it from the template
 * the request resolved to removes the chance to forget.
 */
describe('TemplateMarker', function (): void {
    it('emits nothing before a template has been resolved', function (): void {
        $marker = new TemplateMarker;

        ob_start();
        $marker->emit();

        expect(ob_get_clean())->toBe('');
    });

    it('names the template in the WordPress hierarchy', function (): void {
        $marker = new TemplateMarker;

        expect($marker->name('/srv/site/themes/apiary/resources/views/single.blade.php'))->toBe('single')
            ->and($marker->name('/srv/site/themes/apiary/index.php'))->toBe('index')
            ->and($marker->name('/srv/site/themes/apiary/resources/views/errors/404.blade.php'))->toBe('404');
    });

    it('hands the template back untouched', function (): void {
        $marker = new TemplateMarker;

        // It is a filter on template_include: changing the value would change
        // what WordPress renders.
        expect($marker->capture('/srv/site/themes/apiary/resources/views/page.blade.php'))
            ->toBe('/srv/site/themes/apiary/resources/views/page.blade.php')
            ->and($marker->capture(false))->toBeFalse();
    });

    it('emits the name and a path for the template it captured', function (): void {
        $marker = new TemplateMarker;
        $marker->capture('/srv/site/themes/apiary/resources/views/category.blade.php');

        ob_start();
        $marker->emit();
        $output = ob_get_clean();

        expect($output)->toContain('pollora:template="category"')
            ->and($output)->toStartWith('<!--')
            ->and(trim($output))->toEndWith('-->');
    });

    it('keeps the comment from ending early', function (): void {
        $marker = new TemplateMarker;

        // A template path is not user input, but it travels through a filter
        // any plugin can touch, and "-->" inside a comment closes it.
        $marker->capture('/srv/site/themes/x/--><script>alert(1)</script>.blade.php');

        ob_start();
        $marker->emit();
        $output = ob_get_clean();

        expect($output)->not->toContain('<script>')
            ->and(substr_count($output, '-->'))->toBe(1);
    });

    it('emits once even when wp_head fires twice', function (): void {
        // Measured on a front page: wp_head ran twice and the marker appeared
        // twice, which reads like two templates answered.
        $marker = new TemplateMarker;
        $marker->capture('/srv/site/themes/apiary/resources/views/home.blade.php');

        ob_start();
        $marker->emit();
        $marker->emit();

        expect(substr_count(ob_get_clean(), 'pollora:template'))->toBe(1);
    });

    it('does not leak where the site lives on disk', function (): void {
        $marker = new TemplateMarker;
        $marker->capture('/home/someone/secret-project/themes/apiary/resources/views/single.blade.php');

        ob_start();
        $marker->emit();
        $output = ob_get_clean();

        // base_path() is not resolvable here, so it falls back to the
        // basename rather than printing an absolute path.
        expect($output)->not->toContain('/home/someone');
    });
});
