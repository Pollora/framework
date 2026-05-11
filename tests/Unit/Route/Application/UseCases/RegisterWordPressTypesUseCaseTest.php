<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Mockery as m;
use Pollora\Route\Application\UseCases\RegisterWordPressTypesUseCase;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressTypeResolverInterface;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    $this->typeResolver = m::mock(WordPressTypeResolverInterface::class);
    $this->logger = m::mock(LoggerInterface::class);

    $this->useCase = new RegisterWordPressTypesUseCase(
        $this->typeResolver,
        $this->logger
    );

    if (! class_exists('WP_Post')) {
        eval('namespace { class WP_Post { public int $ID = 1; } }');
    }
});

describe('RegisterWordPressTypesUseCase', function (): void {

    it('registers all five WordPress types in container', function (): void {
        $container = new Container;

        $this->typeResolver->shouldReceive('resolvePost')->andReturn(new WP_Post);
        $this->typeResolver->shouldReceive('resolveTerm')->andReturn(null);
        $this->typeResolver->shouldReceive('resolveUser')->andReturn(null);
        $this->typeResolver->shouldReceive('resolveQuery')->andReturn(null);
        $this->typeResolver->shouldReceive('resolveWP')->andReturn(null);

        $this->useCase->execute($container);

        expect($container->bound('WP_Post'))->toBeTrue();
        expect($container->bound('WP_Term'))->toBeTrue();
        expect($container->bound('WP_User'))->toBeTrue();
        expect($container->bound('WP_Query'))->toBeTrue();
        expect($container->bound('WP'))->toBeTrue();
    });

    it('resolves types safely without throwing', function (): void {
        $container = new Container;

        $this->typeResolver->shouldReceive('resolvePost')->andThrow(new RuntimeException('WP not loaded'));
        $this->typeResolver->shouldReceive('resolveTerm')->andReturn(null);
        $this->typeResolver->shouldReceive('resolveUser')->andReturn(null);
        $this->typeResolver->shouldReceive('resolveQuery')->andReturn(null);
        $this->typeResolver->shouldReceive('resolveWP')->andReturn(null);

        $this->logger->shouldReceive('error')
            ->once()
            ->with('WordPress type resolution failed', m::type('array'));

        $this->useCase->execute($container);

        $result = $container->make('WP_Post');
        expect($result)->toBeNull();
    });

    it('works without a logger', function (): void {
        $useCase = new RegisterWordPressTypesUseCase($this->typeResolver);
        $container = new Container;

        $this->typeResolver->shouldReceive('resolvePost')->andThrow(new RuntimeException('WP not loaded'));
        $this->typeResolver->shouldReceive('resolveTerm')->andReturn(null);
        $this->typeResolver->shouldReceive('resolveUser')->andReturn(null);
        $this->typeResolver->shouldReceive('resolveQuery')->andReturn(null);
        $this->typeResolver->shouldReceive('resolveWP')->andReturn(null);

        $useCase->execute($container);

        $result = $container->make('WP_Post');
        expect($result)->toBeNull();
    });

    it('resolves registered types correctly', function (): void {
        $container = new Container;
        $post = new WP_Post;

        $this->typeResolver->shouldReceive('resolvePost')->andReturn($post);
        $this->typeResolver->shouldReceive('resolveTerm')->andReturn(null);
        $this->typeResolver->shouldReceive('resolveUser')->andReturn(null);
        $this->typeResolver->shouldReceive('resolveQuery')->andReturn(null);
        $this->typeResolver->shouldReceive('resolveWP')->andReturn(null);

        $this->useCase->execute($container);

        expect($container->make('WP_Post'))->toBe($post);
        expect($container->make('WP_Term'))->toBeNull();
    });
});
