<?php

declare(strict_types=1);

use Pollora\Taxonomy\Domain\Contracts\TaxonomyRegistryInterface;
use Pollora\Taxonomy\Infrastructure\Repositories\TaxonomyRepository;

function covRepositoryTaxonomy(string $slug): object
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

describe('TaxonomyRepository', function (): void {
    beforeEach(function (): void {
        $this->registry = Mockery::mock(TaxonomyRegistryInterface::class);
        $this->repository = new TaxonomyRepository($this->registry);
    });

    it('registers a saved taxonomy with WordPress and remembers it', function (): void {
        $genre = covRepositoryTaxonomy('genre');
        $this->registry->shouldReceive('register')->once()->with($genre)->andReturn(true);

        expect($this->repository->save($genre))->toBeTrue()
            ->and($this->repository->findBySlug('genre'))->toBe($genre)
            ->and($this->repository->findAll())->toBe(['genre' => $genre])
            ->and($this->repository->count())->toBe(1);
    });

    it('returns the registry answer when WordPress refuses the registration', function (): void {
        $this->registry->shouldReceive('register')->once()->andReturn(false);

        expect($this->repository->save(covRepositoryTaxonomy('genre')))->toBeFalse();
    });

    it('refuses to save a taxonomy without a slug', function (): void {
        $this->registry->shouldNotReceive('register');

        expect($this->repository->save(covRepositoryTaxonomy('')))->toBeFalse()
            ->and($this->repository->save(new stdClass))->toBeFalse()
            ->and($this->repository->count())->toBe(0);
    });

    it('cannot rebuild a taxonomy that only WordPress knows about', function (): void {
        $this->registry->shouldReceive('exists')->with('legacy')->andReturn(true);

        expect($this->repository->findBySlug('legacy'))->toBeNull();
    });

    it('finds nothing for an unknown slug', function (): void {
        $this->registry->shouldReceive('exists')->with('nope')->andReturn(false);

        expect($this->repository->findBySlug('nope'))->toBeNull();
    });

    it('knows a taxonomy from its own memory without asking WordPress', function (): void {
        $this->registry->shouldReceive('register')->andReturn(true);
        $this->registry->shouldNotReceive('exists');
        $this->repository->save(covRepositoryTaxonomy('genre'));

        expect($this->repository->exists('genre'))->toBeTrue();
    });

    it('falls back on WordPress to tell whether a taxonomy exists', function (): void {
        $this->registry->shouldReceive('exists')->with('legacy')->andReturn(true);
        $this->registry->shouldReceive('exists')->with('nope')->andReturn(false);

        expect($this->repository->exists('legacy'))->toBeTrue()
            ->and($this->repository->exists('nope'))->toBeFalse();
    });

    it('forgets a removed taxonomy and tolerates removing an unknown one', function (): void {
        $this->registry->shouldReceive('register')->andReturn(true);
        $this->repository->save(covRepositoryTaxonomy('genre'));

        expect($this->repository->remove('genre'))->toBeTrue()
            ->and($this->repository->findAll())->toBe([])
            ->and($this->repository->remove('unknown'))->toBeTrue();
    });

    it('empties its memory when the cache is cleared', function (): void {
        $this->registry->shouldReceive('register')->andReturn(true);
        $this->repository->save(covRepositoryTaxonomy('genre'));
        $this->repository->save(covRepositoryTaxonomy('theme'));

        $this->repository->clearCache();

        expect($this->repository->count())->toBe(0);
    });

    it('exposes the taxonomies WordPress has registered', function (): void {
        $this->registry->shouldReceive('getAll')->once()->andReturn(['genre' => 'x']);

        expect($this->repository->getWordPressTaxonomies())->toBe(['genre' => 'x']);
    });
});
