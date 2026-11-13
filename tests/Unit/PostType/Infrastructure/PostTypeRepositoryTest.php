<?php

declare(strict_types=1);

use Pollora\PostType\Domain\Contracts\PostTypeRegistryInterface;
use Pollora\PostType\Infrastructure\Repositories\PostTypeRepository;

function covRepositoryPostType(string $slug): object
{
    return new class($slug)
    {
        public function __construct(private readonly string $slug) {}

        public function getSlug(): string
        {
            return $this->slug;
        }
    };
}

describe('PostTypeRepository', function (): void {
    beforeEach(function (): void {
        $this->registry = Mockery::mock(PostTypeRegistryInterface::class);
        $this->repository = new PostTypeRepository($this->registry);
    });

    it('registers a saved post type with WordPress and remembers it', function (): void {
        $book = covRepositoryPostType('book');
        $this->registry->shouldReceive('register')->once()->with($book)->andReturn(true);

        expect($this->repository->save($book))->toBeTrue()
            ->and($this->repository->findBySlug('book'))->toBe($book)
            ->and($this->repository->findAll())->toBe(['book' => $book])
            ->and($this->repository->count())->toBe(1);
    });

    it('returns the registry answer when WordPress refuses the registration', function (): void {
        $this->registry->shouldReceive('register')->once()->andReturn(false);

        expect($this->repository->save(covRepositoryPostType('book')))->toBeFalse();
    });

    it('refuses to save a post type without a slug', function (): void {
        $this->registry->shouldNotReceive('register');

        expect($this->repository->save(covRepositoryPostType('')))->toBeFalse()
            ->and($this->repository->save(new stdClass))->toBeFalse()
            ->and($this->repository->count())->toBe(0);
    });

    it('cannot rebuild a post type that only WordPress knows about', function (): void {
        $this->registry->shouldReceive('exists')->with('legacy')->andReturn(true);

        expect($this->repository->findBySlug('legacy'))->toBeNull();
    });

    it('finds nothing for an unknown slug', function (): void {
        $this->registry->shouldReceive('exists')->with('nope')->andReturn(false);

        expect($this->repository->findBySlug('nope'))->toBeNull();
    });

    it('knows a post type from its own memory without asking WordPress', function (): void {
        $this->registry->shouldReceive('register')->andReturn(true);
        $this->registry->shouldNotReceive('exists');
        $this->repository->save(covRepositoryPostType('book'));

        expect($this->repository->exists('book'))->toBeTrue();
    });

    it('falls back on WordPress to tell whether a post type exists', function (): void {
        $this->registry->shouldReceive('exists')->with('legacy')->andReturn(true);
        $this->registry->shouldReceive('exists')->with('nope')->andReturn(false);

        expect($this->repository->exists('legacy'))->toBeTrue()
            ->and($this->repository->exists('nope'))->toBeFalse();
    });

    it('forgets a removed post type and tolerates removing an unknown one', function (): void {
        $this->registry->shouldReceive('register')->andReturn(true);
        $this->repository->save(covRepositoryPostType('book'));

        expect($this->repository->remove('book'))->toBeTrue()
            ->and($this->repository->findAll())->toBe([])
            ->and($this->repository->remove('unknown'))->toBeTrue();
    });

    it('empties its memory when the cache is cleared', function (): void {
        $this->registry->shouldReceive('register')->andReturn(true);
        $this->repository->save(covRepositoryPostType('book'));
        $this->repository->save(covRepositoryPostType('movie'));

        $this->repository->clearCache();

        expect($this->repository->count())->toBe(0);
    });

    it('exposes the post types WordPress has registered', function (): void {
        $this->registry->shouldReceive('getAll')->once()->andReturn(['book' => 'x']);

        expect($this->repository->getWordPressPostTypes())->toBe(['book' => 'x']);
    });
});
