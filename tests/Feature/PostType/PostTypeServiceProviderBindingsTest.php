<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;
use Pollora\PostType\Domain\Contracts\PostTypeRegistryInterface;
use Pollora\PostType\Domain\Contracts\PostTypeRepositoryInterface;
use Pollora\PostType\Domain\Contracts\PostTypeServiceInterface;
use Pollora\PostType\Infrastructure\Adapters\WordPressPostTypeRegistry;
use Pollora\PostType\Infrastructure\Factories\PostTypeFactory;
use Pollora\PostType\Infrastructure\Providers\PostTypeServiceProvider;
use Pollora\PostType\Infrastructure\Repositories\PostTypeRepository;
use Pollora\PostType\Infrastructure\Services\PostTypeDiscovery;
use Pollora\PostType\Application\Services\PostTypeService;
use Pollora\PostType\UI\Console\PostTypeMakeCommand;

beforeEach(function (): void {
    $this->app->register(PostTypeServiceProvider::class);
});

describe('PostTypeServiceProvider', function (): void {
    it('binds the post type contracts to their WordPress implementations', function (): void {
        expect($this->app->make(PostTypeFactoryInterface::class))->toBeInstanceOf(PostTypeFactory::class)
            ->and($this->app->make(PostTypeRegistryInterface::class))->toBeInstanceOf(WordPressPostTypeRegistry::class)
            ->and($this->app->make(PostTypeRepositoryInterface::class))->toBeInstanceOf(PostTypeRepository::class)
            ->and($this->app->make(PostTypeServiceInterface::class))->toBeInstanceOf(PostTypeService::class);
    });

    it('shares one instance of each service', function (): void {
        foreach ([PostTypeRegistryInterface::class, PostTypeRepositoryInterface::class, PostTypeServiceInterface::class, PostTypeDiscovery::class] as $abstract) {
            expect($this->app->make($abstract))->toBe($this->app->make($abstract));
        }
    });

    it('keeps the concrete service and its interface as the same object', function (): void {
        expect($this->app->make(PostTypeService::class))->toBe($this->app->make(PostTypeServiceInterface::class));
    });

    it('builds the discovery with the post type service', function (): void {
        expect($this->app->make(PostTypeDiscovery::class))->toBeInstanceOf(PostTypeDiscovery::class);
    });

    it('offers the make command in the console', function (): void {
        $commands = Artisan::all();

        $made = array_filter($commands, fn ($command): bool => $command instanceof PostTypeMakeCommand);

        expect(count($made))->toBeGreaterThan(0);
    });
});
