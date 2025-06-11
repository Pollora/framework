<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Pollora\Route\UI\Http\Controllers\FrontendController;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../../../../helpers.php';

beforeEach(function () {
    setupWordPressMocks();
    $this->templateFinder = Mockery::mock(TemplateFinderInterface::class);
    $this->controller = new FrontendController($this->templateFinder);
});

afterEach(function () {
    Mockery::close();
});

describe('FrontendController', function () {
    it('aborts when themes disabled', function () {
        setWordPressFunction('wp_using_themes', fn () => false);
        $request = Request::create('/test');

        expect(fn () => $this->controller->handle($request))
            ->toThrow(HttpException::class, 'Themes are disabled');
    });

    it('renders blade view when available', function () {
        setWordPressFunction('wp_using_themes', fn () => true);
        setWordPressFunction('is_page', fn () => true);
        setWordPressFunction('get_page_template', fn () => '/theme/page.php');
        setWordPressFunction('apply_filters', fn ($filter, $value) => $value);

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
    });

    it('falls back to php template', function () {
        $templatePath = __DIR__.'/test-template.php';
        setWordPressFunction('wp_using_themes', fn () => true);
        setWordPressFunction('is_page', fn () => true);
        setWordPressFunction('get_page_template', fn () => $templatePath);
        setWordPressFunction('apply_filters', fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->with($templatePath)
            ->andReturn(null);

        $request = Request::create('/test');
        $response = $this->controller->handle($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getContent())->toBe('This is a PHP template');
    });

    it('throws 404 when no template', function () {
        setWordPressFunction('wp_using_themes', fn () => true);

        setWordPressConditions([
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

        setWordPressFunction('get_index_template', fn () => '');
        setWordPressFunction('apply_filters', fn ($filter, $value) => $value);

        $this->templateFinder->shouldReceive('getViewNameFromPath')
            ->with('')
            ->andReturn(null);

        $request = Request::create('/test');

        expect(fn () => $this->controller->handle($request))
            ->toThrow(HttpException::class);
    });
});
