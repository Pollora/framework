<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Pollora\Route\Application\Services\AnsweringTemplate;
use Pollora\Route\Domain\Enums\TemplateOutcome;
use Pollora\Route\UI\Http\Controllers\FrontendController;
use Pollora\Route\UI\Http\Responses\SetupRequiredResponse;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

beforeEach(function (): void {
    $this->templateFinder = Mockery::mock(TemplateFinderInterface::class);
    $this->controller = new FrontendController($this->templateFinder);
});

describe('FrontendController', function (): void {
    it('returns 404 when themes disabled', function (): void {
        Brain\Monkey\Functions\when('wp_using_themes')->justReturn(false);

        View::shouldReceive('exists')->andReturn(false);
        View::shouldReceive('replaceNamespace')->andReturnNull();
        View::shouldReceive('addNamespace')->andReturnNull();

        $request = Request::create('/test');
        $response = $this->controller->handle($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getStatusCode())->toBe(404);
    });

    it('renders blade view when available', function (): void {
        Brain\Monkey\Functions\when('wp_using_themes')->justReturn(true);
        Brain\Monkey\Functions\when('is_page')->justReturn(true);
        Brain\Monkey\Functions\when('is_404')->justReturn(false);
        Brain\Monkey\Functions\when('get_page_template')->justReturn('/theme/page.php');
        Brain\Monkey\Functions\when('apply_filters')->alias(fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->with('/theme/page.php')
            ->andReturn('templates.page');

        View::shouldReceive('exists')
            ->with('templates.page')
            ->andReturn(true);
        View::shouldReceive('make')
            ->with('templates.page')
            ->andReturn('<html>Blade page content</html>');

        $request = Request::create('/test');
        $response = $this->controller->handle($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getContent())->toBe('<html>Blade page content</html>');
        expect($response->getStatusCode())->toBe(200);
    });

    it('falls back to php template', function (): void {
        $templatePath = __DIR__.'/test-template.php';
        Brain\Monkey\Functions\when('wp_using_themes')->justReturn(true);
        Brain\Monkey\Functions\when('is_page')->justReturn(true);
        Brain\Monkey\Functions\when('is_404')->justReturn(false);
        Brain\Monkey\Functions\when('get_page_template')->justReturn($templatePath);
        Brain\Monkey\Functions\when('apply_filters')->alias(fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->with($templatePath)
            ->andReturn(null);

        $request = Request::create('/test');
        $response = $this->controller->handle($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getContent())->toBe('This is a PHP template');
    });

    it("answers 404 for a block theme's own 404.html, resolved through template-canvas.php", function (): void {
        // A block theme's 404.html is resolved by WordPress core to a temporary
        // wp-includes/template-canvas.php, never a Blade view: get_404_template()
        // found a real template, so this never reaches the index-fallback branch.
        $templatePath = __DIR__.'/test-template.php';
        Brain\Monkey\Functions\when('wp_using_themes')->justReturn(true);
        Brain\Monkey\Functions\when('is_embed')->justReturn(false);
        Brain\Monkey\Functions\when('is_404')->justReturn(true);
        Brain\Monkey\Functions\when('get_404_template')->justReturn($templatePath);
        Brain\Monkey\Functions\when('apply_filters')->alias(fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->with($templatePath)
            ->andReturn(null);

        $request = Request::create('/nonexistent');
        $response = $this->controller->handle($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getStatusCode())->toBe(404);
        expect($response->getContent())->toBe('This is a PHP template');
    });

    it('returns 404 response when no template found', function (): void {
        Brain\Monkey\Functions\when('wp_using_themes')->justReturn(true);

        Brain\Monkey\Functions\stubs([
            'is_page' => false,
            'is_singular' => false,
            'is_archive' => false,
            'is_404' => false,
            'is_search' => false,
            'is_front_page' => false,
            'is_home' => false,
            'is_privacy_policy' => false,
            'is_post_type_archive' => false,
            'is_tax' => false,
            'is_attachment' => false,
            'is_single' => false,
            'is_category' => false,
            'is_tag' => false,
            'is_author' => false,
            'is_date' => false,
            'is_embed' => false,
        ]);

        Brain\Monkey\Functions\when('get_index_template')->justReturn('');
        Brain\Monkey\Functions\when('apply_filters')->alias(fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->with('')
            ->andReturn(null);

        View::shouldReceive('exists')->andReturn(false);
        View::shouldReceive('replaceNamespace')->andReturnNull();
        View::shouldReceive('addNamespace')->andReturnNull();

        $request = Request::create('/test');
        $response = $this->controller->handle($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getStatusCode())->toBe(404);
    });

    it('rejects non-php template paths for security', function (): void {
        $nonPhpPath = __DIR__.'/../../../../../../composer.json';
        Brain\Monkey\Functions\when('wp_using_themes')->justReturn(true);
        Brain\Monkey\Functions\when('is_page')->justReturn(true);
        Brain\Monkey\Functions\when('is_404')->justReturn(false);
        Brain\Monkey\Functions\when('get_page_template')->justReturn($nonPhpPath);
        Brain\Monkey\Functions\when('apply_filters')->alias(fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->andReturn(null);

        View::shouldReceive('exists')->andReturn(false);
        View::shouldReceive('replaceNamespace')->andReturnNull();
        View::shouldReceive('addNamespace')->andReturnNull();

        $request = Request::create('/test');
        $response = $this->controller->handle($request);

        // Should return 404 because non-.php file is rejected by isAllowedTemplatePath
        expect($response->getStatusCode())->toBe(404);
    });

    it('renders Laravel error page when 404 with index fallback', function (): void {
        Brain\Monkey\Functions\when('wp_using_themes')->justReturn(true);

        Brain\Monkey\Functions\stubs([
            'is_embed' => false,
            'is_404' => true,
            'is_search' => false,
            'is_front_page' => false,
            'is_home' => false,
            'is_privacy_policy' => false,
            'is_post_type_archive' => false,
            'is_tax' => false,
            'is_attachment' => false,
            'is_single' => false,
            'is_page' => false,
            'is_singular' => false,
            'is_category' => false,
            'is_tag' => false,
            'is_author' => false,
            'is_date' => false,
            'is_archive' => false,
        ]);

        Brain\Monkey\Functions\when('get_404_template')->justReturn('');
        Brain\Monkey\Functions\when('get_index_template')->justReturn('/theme/index.php');
        Brain\Monkey\Functions\when('apply_filters')->alias(fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->with('/theme/index.php')
            ->andReturn('index');

        View::shouldReceive('exists')
            ->with('errors.404')
            ->andReturn(false);
        View::shouldReceive('replaceNamespace')->andReturnNull();
        View::shouldReceive('addNamespace')->andReturnNull();
        View::shouldReceive('exists')
            ->with('errors::404')
            ->andReturn(false);

        $request = Request::create('/nonexistent');
        $response = $this->controller->handle($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getStatusCode())->toBe(404);
        expect($response->getContent())->toBe('Not Found');
    });

    it('remembers the condition and the Blade view that answered', function (): void {
        $answering = new AnsweringTemplate;
        $controller = new FrontendController($this->templateFinder, $answering);

        Brain\Monkey\Functions\when('wp_using_themes')->justReturn(true);
        Brain\Monkey\Functions\stubs([
            'is_embed' => false,
            'is_404' => false,
            'is_search' => false,
            'is_front_page' => false,
            'is_home' => false,
            'is_privacy_policy' => false,
            'is_post_type_archive' => false,
            'is_tax' => false,
            'is_attachment' => false,
            'is_single' => true,
        ]);
        Brain\Monkey\Functions\when('get_single_template')->justReturn('/theme/single.blade.php');
        Brain\Monkey\Functions\when('apply_filters')->alias(fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')->andReturn('single');
        View::shouldReceive('exists')->with('single')->andReturn(true);
        View::shouldReceive('make')->with('single')->andReturn('<html>single</html>');

        $controller->handle(Request::create('/hello-world'));

        $resolution = $answering->resolution();

        expect($resolution)->not->toBeNull()
            ->and($resolution->template)->toBe('/theme/single.blade.php')
            ->and($resolution->condition)->toBe('is_single')
            ->and($resolution->view)->toBe('single')
            ->and($resolution->usedIndexFallback)->toBeFalse()
            ->and($resolution->outcome)->toBe(TemplateOutcome::View);
    });

    it('remembers a 404 that only the index could answer as not found', function (): void {
        $answering = new AnsweringTemplate;
        $controller = new FrontendController($this->templateFinder, $answering);

        Brain\Monkey\Functions\when('wp_using_themes')->justReturn(true);
        Brain\Monkey\Functions\stubs([
            'is_embed' => false,
            'is_404' => true,
            'is_search' => false,
            'is_front_page' => false,
            'is_home' => false,
            'is_privacy_policy' => false,
            'is_post_type_archive' => false,
            'is_tax' => false,
            'is_attachment' => false,
            'is_single' => false,
            'is_page' => false,
            'is_singular' => false,
            'is_category' => false,
            'is_tag' => false,
            'is_author' => false,
            'is_date' => false,
            'is_archive' => false,
        ]);
        Brain\Monkey\Functions\when('get_404_template')->justReturn('');
        Brain\Monkey\Functions\when('get_index_template')->justReturn('/theme/index.php');
        Brain\Monkey\Functions\when('apply_filters')->alias(fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')->andReturn('index');
        View::shouldReceive('exists')->andReturn(false);
        View::shouldReceive('replaceNamespace')->andReturnNull();
        View::shouldReceive('addNamespace')->andReturnNull();

        $controller->handle(Request::create('/nothing-here'));

        expect($answering->resolution()?->condition)->toBeNull()
            ->and($answering->resolution()?->usedIndexFallback)->toBeTrue()
            ->and($answering->resolution()?->outcome)->toBe(TemplateOutcome::NotFound);
    });

    it('leaves nothing behind for requests the hierarchy never saw', function (): void {
        expect((new AnsweringTemplate)->resolution())->toBeNull();
    });
});

/**
 * Pollora loads WordPress only once the database is configured, but the
 * fallback route is always there: it used to call WordPress anyway and
 * answer 500 (Pollora/pollora#78).
 */
describe('FrontendController before WordPress is set up', function (): void {
    beforeEach(function (): void {
        $this->controller = new class($this->templateFinder) extends FrontendController
        {
            protected function wordPressIsLoaded(): bool
            {
                return false;
            }
        };

        config()->set('database.default', 'sqlite');
    });

    it('answers a plain 503 without debug', function (): void {
        config()->set('app.debug', false);

        $response = $this->controller->handle(Request::create('/'));

        expect($response->getStatusCode())->toBe(503)
            ->and($response->getContent())->not->toContain('DB_CONNECTION');
    });

    it('names the missing settings and the commands to run with debug', function (): void {
        config()->set('app.debug', true);
        config()->set('database.connections.mysql.host');

        $response = $this->controller->handle(Request::create('/'));

        expect($response->getStatusCode())->toBe(503)
            ->and($response->getContent())
            ->toContain('Pollora is not set up yet')
            ->toContain('DB_CONNECTION=mysql')
            ->toContain('DB_HOST')
            ->toContain('php artisan pollora:env:setup')
            ->toContain('php artisan pollora:install')
            ->toContain('php artisan pollora:doctor');
    });

    it('asks only for what a MySQL connection still lacks', function (): void {
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'database' => '',
            'username' => 'pollora',
            'password' => '',
        ]);

        expect(SetupRequiredResponse::missingSettings())->toBe(['DB_DATABASE']);
    });
});
