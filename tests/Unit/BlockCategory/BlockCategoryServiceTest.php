<?php

declare(strict_types=1);

use Pollora\BlockCategory\Application\Services\BlockCategoryService;
use Pollora\BlockCategory\Domain\Contracts\BlockCategoryRegistrarInterface;
use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Collection\Domain\Contracts\CollectionInterface;
use Pollora\Collection\Infrastructure\Adapters\LaravelCollectionAdapter;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;

function blockCategoryCollection(array $items): CollectionInterface
{
    return new LaravelCollectionAdapter($items);
}

beforeEach(function (): void {
    $this->config = Mockery::mock(ConfigRepositoryInterface::class);
    $this->factory = Mockery::mock(CollectionFactoryInterface::class);
    $this->registrar = Mockery::mock(BlockCategoryRegistrarInterface::class);
    $this->service = new BlockCategoryService($this->config, $this->factory, $this->registrar);
});

afterEach(function (): void {
    Mockery::close();
});

describe('BlockCategoryService', function (): void {
    it('registers nothing when no block category is configured', function (): void {
        $this->config->shouldReceive('get')->with('theme.gutenberg.categories.blocks', [])->andReturn([]);
        $this->registrar->shouldNotReceive('registerCategories');

        $this->service->registerConfiguredCategories();
    });

    it('registers nothing when the configured collection ends up empty', function (): void {
        $this->config->shouldReceive('get')->andReturn(['x' => ['label' => 'X']]);
        $this->factory->shouldReceive('make')->andReturn(blockCategoryCollection([]));
        $this->registrar->shouldNotReceive('registerCategories');

        $this->service->registerConfiguredCategories();
    });

    it('registers each configured category with its slug and its label, falling back to title then to an empty string', function (): void {
        $configured = [
            'news' => ['label' => 'News'],
            'shop' => ['title' => 'Shop'],
            'misc' => [],
        ];
        $this->config->shouldReceive('get')->andReturn($configured);
        $this->factory->shouldReceive('make')->with($configured)->andReturn(blockCategoryCollection($configured));
        $this->registrar->shouldReceive('registerCategories')->once()->with(Mockery::on(
            fn (CollectionInterface $c): bool => $c->all() === [
                'news' => ['slug' => 'news', 'title' => 'News'],
                'shop' => ['slug' => 'shop', 'title' => 'Shop'],
                'misc' => ['slug' => 'misc', 'title' => ''],
            ]
        ));

        $this->service->registerConfiguredCategories();
    });
});
