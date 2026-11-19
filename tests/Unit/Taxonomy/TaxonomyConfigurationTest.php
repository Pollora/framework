<?php

declare(strict_types=1);

use Pollora\Taxonomy\Infrastructure\Services\TaxonomyConfiguration;

describe('TaxonomyConfiguration', function (): void {
    beforeEach(function (): void {
        $this->config = new TaxonomyConfiguration('genre', 'Genre', 'Genres', 'book', ['hierarchical' => true]);
    });

    it('describes the taxonomy it was built for', function (): void {
        expect($this->config->getSlug())->toBe('genre')
            ->and($this->config->getName())->toBe('Genre')
            ->and($this->config->getPluralName())->toBe('Genres')
            ->and($this->config->getObjectType())->toBe('book')
            ->and($this->config->withArgs())->toBe(['hierarchical' => true]);
    });

    it('can attach the taxonomy to other object types', function (): void {
        $this->config->setObjectType(['book', 'movie']);

        expect($this->config->getObjectType())->toBe(['book', 'movie']);
    });

    it('prefers the object types an attribute stored in the arguments', function (): void {
        $this->config->setArg('object_type', ['page']);

        expect($this->config->getObjectType())->toBe(['page']);
    });

    it('sets, reads and defaults single arguments', function (): void {
        $this->config->setArg('rewrite', false);

        expect($this->config->getArg('rewrite'))->toBeFalse()
            ->and($this->config->getArg('missing'))->toBeNull()
            ->and($this->config->getArg('missing', 'fallback'))->toBe('fallback');
    });

    it('merges arguments with the new ones winning, and replaces them wholesale', function (): void {
        $this->config->mergeArgs(['hierarchical' => false, 'show_in_rest' => true]);

        expect($this->config->getArgs())->toBe(['hierarchical' => false, 'show_in_rest' => true]);

        $this->config->replaceArgs(['public' => true]);

        expect($this->config->getArgs())->toBe(['public' => true]);
    });

    it('keeps the priority out of the registration arguments and defaults it to 5', function (): void {
        expect($this->config->getPriority())->toBe(5);

        $this->config->setArg('priority', 9);

        expect($this->config->getPriority())->toBe(9)
            ->and($this->config->getArgs())->not->toHaveKey('priority');
    });

    it('gives the whole configuration for registration', function (): void {
        expect($this->config->toArray())->toBe([
            'slug' => 'genre',
            'singular' => 'Genre',
            'plural' => 'Genres',
            'object_type' => 'book',
            'args' => ['hierarchical' => true],
            'priority' => 5,
        ]);
    });
});
