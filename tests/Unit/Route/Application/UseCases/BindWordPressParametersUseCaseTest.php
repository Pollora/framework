<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Mockery as m;
use Pollora\Route\Application\UseCases\BindWordPressParametersUseCase;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressTypeResolverInterface;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    $this->typeResolver = m::mock(WordPressTypeResolverInterface::class);
    $this->logger = m::mock(LoggerInterface::class);

    $this->useCase = new BindWordPressParametersUseCase(
        $this->typeResolver,
        $this->logger
    );

    if (! class_exists('WP_Post')) {
        eval('namespace { class WP_Post { public int $ID = 1; } }');
    }
});

describe('BindWordPressParametersUseCase', function (): void {

    it('binds WordPress parameters to route via type-hints', function (): void {
        $post = new WP_Post;
        $this->typeResolver->shouldReceive('resolve')
            ->with('WP_Post')
            ->andReturn($post);

        $route = new Route(['GET'], '/test', fn (WP_Post $post): string => 'test');
        $route->bind(Request::create('/test'));

        $this->useCase->execute($route);

        expect($route->parameter('post'))->toBe($post);
    });

    it('skips builtin types', function (): void {
        $this->typeResolver->shouldNotReceive('resolve');

        $route = new Route(['GET'], '/test', fn (string $name): string => 'test');
        $route->bind(Request::create('/test'));

        $this->useCase->execute($route);

        expect($route->parameter('name'))->toBeNull();
    });

    it('handles exceptions gracefully', function (): void {
        $this->typeResolver->shouldReceive('resolve')
            ->andThrow(new RuntimeException('WP not loaded'));

        $this->logger->shouldReceive('error')
            ->once()
            ->with('Failed to bind WordPress parameters', m::type('array'));

        $route = new Route(['GET'], '/test', fn (WP_Post $post): string => 'test');
        $route->bind(Request::create('/test'));

        $this->useCase->execute($route);

        expect($route->parameter('post'))->toBeNull();
    });

    it('skips routes without callable uses', function (): void {
        $this->typeResolver->shouldNotReceive('resolve');

        // Route with no 'uses' key (e.g., middleware-only route)
        $route = new Route(['GET'], '/test', fn (): string => 'test');
        $route->bind(Request::create('/test'));

        // Override action to simulate non-callable uses
        $route->setAction(array_merge($route->getAction(), ['uses' => null]));

        $this->useCase->execute($route);
    });

    it('works without a logger', function (): void {
        $useCase = new BindWordPressParametersUseCase($this->typeResolver);

        $this->typeResolver->shouldReceive('resolve')
            ->andThrow(new RuntimeException('WP not loaded'));

        $route = new Route(['GET'], '/test', fn (WP_Post $post): string => 'test');
        $route->bind(Request::create('/test'));

        $useCase->execute($route);

        expect($route->parameter('post'))->toBeNull();
    });

    it('resolves multiple type-hinted parameters', function (): void {
        if (! class_exists('WP_User')) {
            eval('namespace { class WP_User { public int $ID = 1; } }');
        }

        $post = new WP_Post;
        $user = new WP_User;

        $this->typeResolver->shouldReceive('resolve')
            ->with('WP_Post')
            ->andReturn($post);
        $this->typeResolver->shouldReceive('resolve')
            ->with('WP_User')
            ->andReturn($user);

        $route = new Route(['GET'], '/test', fn (WP_Post $post, WP_User $user): string => 'test');
        $route->bind(Request::create('/test'));

        $this->useCase->execute($route);

        expect($route->parameter('post'))->toBe($post);
        expect($route->parameter('user'))->toBe($user);
    });
});
