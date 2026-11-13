<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Mockery as m;
use Pollora\Route\Application\UseCases\BindWordPressParametersUseCase;
use Pollora\Route\Domain\Contracts\WordPressTypeResolverInterface;
use Pollora\Route\Infrastructure\Models\Route;

beforeEach(function (): void {
    if (! class_exists('WP_Post')) {
        eval('namespace { class WP_Post { public int $ID = 1; public function __construct($post = null) {} } }');
    }

    $this->typeResolver = m::mock(WordPressTypeResolverInterface::class);
    $this->useCase = new BindWordPressParametersUseCase($this->typeResolver);
});

describe('BindWordPressParametersUseCase parameter kinds', function (): void {
    it('only asks WordPress for parameters that carry a single class type', function (): void {
        $post = new WP_Post;
        $this->typeResolver->shouldReceive('resolve')->once()->with('WP_Post')->andReturn($post);

        $route = new Route(['GET'], '/post', fn (WP_Post $post, int $page, $untyped, WP_Post|WP_Term|null $either): string => 'show');
        $route->bind(Request::create('/post'));

        $this->useCase->execute($route);

        expect($route->parameter('post'))->toBe($post)
            ->and($route->parameter('page'))->toBeNull()
            ->and($route->parameter('untyped'))->toBeNull()
            ->and($route->parameter('either'))->toBeNull();
    });

    it('leaves a parameter unbound when WordPress has no such object for this request', function (): void {
        $this->typeResolver->shouldReceive('resolve')->with('WP_Post')->andReturnNull();

        $route = new Route(['GET'], '/post', fn (WP_Post $post): string => 'show');
        $route->bind(Request::create('/post'));

        $this->useCase->execute($route);

        expect($route->parameter('post'))->toBeNull();
    });

    it('does not fail, nor log, when something goes wrong and there is no logger', function (): void {
        $this->typeResolver->shouldReceive('resolve')->andThrow(new RuntimeException('WP not loaded'));

        $route = new Route(['GET'], '/post', fn (WP_Post $post): string => 'show');
        $route->bind(Request::create('/post'));

        $this->useCase->execute($route);

        expect($route->parameter('post'))->toBeNull();
    });
});
