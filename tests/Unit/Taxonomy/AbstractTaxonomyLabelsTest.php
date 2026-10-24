<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\Taxonomy\Domain\Contracts\LabelTranslatorInterface;
use Pollora\Taxonomy\Domain\Models\AbstractTaxonomy;
use Pollora\Taxonomy\Infrastructure\Providers\TaxonomyServiceProvider;
use Pollora\Taxonomy\Infrastructure\Services\WordPressLabelTranslator;

/**
 * The default labels of an AbstractTaxonomy go through the label translator
 * port, not through a global translation function the domain would call.
 */
class ProjectCategory extends AbstractTaxonomy {}

afterEach(function (): void {
    AbstractTaxonomy::useLabelTranslator(null);
});

describe('AbstractTaxonomy labels', function (): void {
    it('uses the label templates untranslated when no translator is set', function (): void {
        $labels = (new ProjectCategory)->getLabels();

        expect($labels)->toMatchArray([
            'name' => 'Project categories',
            'singular_name' => 'Project category',
            'all_items' => 'All Project categories',
            'edit_item' => 'Edit Project category',
            'parent_item_colon' => 'Parent Project category:',
        ]);
    });

    it('translates every label template in the pollora text domain', function (): void {
        $asked = new ArrayObject;
        AbstractTaxonomy::useLabelTranslator(new class($asked) implements LabelTranslatorInterface
        {
            public function __construct(private readonly ArrayObject $asked) {}

            public function translate(string $text, string $domain): string
            {
                $this->asked[] = $domain;

                return '['.$text.']';
            }
        });

        $labels = (new ProjectCategory)->getLabels();

        expect($labels['all_items'])->toBe('[All Project categories]')
            ->and($labels['search_items'])->toBe('[Search Project categories]')
            ->and($labels['name'])->toBe('Project categories')
            ->and($asked)->toHaveCount(14)
            ->and(array_unique($asked->getArrayCopy()))->toBe(['pollora']);
    });

    it('puts the labels into the registration arguments', function (): void {
        $taxonomy = new ProjectCategory;
        $taxonomy->setArg('hierarchical', true);

        expect($taxonomy->getArgs())->toMatchArray(['hierarchical' => true])
            ->and($taxonomy->getArgs()['labels'])->toBe($taxonomy->getLabels());
    });
});

describe('WordPressLabelTranslator', function (): void {
    it('implements the domain port', function (): void {
        expect(new WordPressLabelTranslator)->toBeInstanceOf(LabelTranslatorInterface::class);
    });

    it('translates through __()', function (): void {
        // The suite installs a passthrough translator behind __()
        expect((new WordPressLabelTranslator)->translate('All %s', 'pollora'))->toBe('All %s');
    });
});

describe('TaxonomyServiceProvider label translator', function (): void {
    it('binds the port and hands it to AbstractTaxonomy', function (): void {
        $app = new class extends Container
        {
            public function runningInConsole(): bool
            {
                return false;
            }
        };

        (new TaxonomyServiceProvider($app))->register();

        $translator = $app->make(LabelTranslatorInterface::class);
        $property = new ReflectionProperty(AbstractTaxonomy::class, 'labelTranslator');

        expect($translator)->toBeInstanceOf(WordPressLabelTranslator::class)
            ->and($property->getValue())->toBe($translator);
    });
});
