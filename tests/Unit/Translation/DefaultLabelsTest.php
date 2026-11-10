<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Attributes\PostType;
use Pollora\Attributes\PostType\Labels;
use Pollora\Attributes\Taxonomy;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\Entity\Domain\Model\PostType as EntityPostType;
use Pollora\I18n\Runtime\Translatable;
use Pollora\PostType\Application\Services\PostTypeService;
use Pollora\PostType\Infrastructure\Adapters\WordPressPostTypeRegistry;
use Pollora\PostType\Infrastructure\Factories\PostTypeFactory;
use Pollora\PostType\Infrastructure\Services\PostTypeDiscovery;
use Pollora\Taxonomy\Application\Services\TaxonomyService;
use Pollora\Taxonomy\Infrastructure\Adapters\WordPressTaxonomyRegistry;
use Pollora\Taxonomy\Infrastructure\Factories\TaxonomyFactory;
use Pollora\Taxonomy\Infrastructure\Services\TaxonomyDiscovery;
use Pollora\Translation\Infrastructure\Adapters\TranslatingEntityRegistry;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;

/** WordPress's stand-in for a domain with no catalogue, which these tests do not load. */
if (! class_exists('NOOP_Translations')) {
    class NOOP_Translations {}
}

/*
 * The default labels of a post type or a taxonomy are built on init, from the singular and
 * plural names translated in the text domain of the entity. Without a catalogue they are
 * the labels the discovery used to build at boot, key for key and in the same order.
 */

/** @return array<string, string> What the discovery built at boot for a post type Book/Books. */
function i18nLabelsBookPostType(): array
{
    return [
        'name' => 'Books',
        'singular_name' => 'Book',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Book',
        'edit_item' => 'Edit Book',
        'new_item' => 'New Book',
        'view_item' => 'View Book',
        'view_items' => 'View Books',
        'search_items' => 'Search Books',
        'not_found' => 'No Books found',
        'not_found_in_trash' => 'No Books found in Trash',
        'parent_item_colon' => 'Parent Book:',
        'all_items' => 'All Books',
        'archives' => 'Book Archives',
        'attributes' => 'Book Attributes',
        'insert_into_item' => 'Insert into Book',
        'uploaded_to_this_item' => 'Uploaded to this Book',
    ];
}

/** @return array<string, string> What the discovery built at boot for a taxonomy Genre/Genres. */
function i18nLabelsGenreTaxonomy(): array
{
    return [
        'name' => 'Genres',
        'singular_name' => 'Genre',
        'menu_name' => 'Genres',
        'all_items' => 'All Genres',
        'edit_item' => 'Edit Genre',
        'view_item' => 'View Genre',
        'update_item' => 'Update Genre',
        'add_new_item' => 'Add New Genre',
        'new_item_name' => 'New Genre Name',
        'search_items' => 'Search Genres',
        'popular_items' => 'Popular Genres',
        'separate_items_with_commas' => 'Separate Genres with commas',
        'add_or_remove_items' => 'Add or remove Genres',
        'choose_from_most_used' => 'Choose from the most used Genres',
        'not_found' => 'No Genres found',
        'parent_item' => 'Parent Genre',
        'parent_item_colon' => 'Parent Genre:',
    ];
}

#[PostType('lbl_plain', singular: 'Book', plural: 'Books')]
final class I18nLabelsPlain {}

#[PostType('lbl_partial', singular: 'Book', plural: 'Books')]
#[Labels(menuName: 'Library', addNew: 'Create')]
final class I18nLabelsPartial {}

#[PostType('lbl_themed', singular: 'Book', plural: 'Books', textDomain: 'my-theme')]
final class I18nLabelsThemed {}

#[PostType('lbl_replaced', singular: 'Book', plural: 'Books')]
#[Labels(menuName: 'Library')]
final class I18nLabelsReplaced
{
    /** @return array<string, mixed> */
    public function withArgs(): array
    {
        return ['labels' => ['name' => 'Only this']];
    }
}

#[PostType('lbl_configured', singular: 'Book', plural: 'Books')]
final class I18nLabelsConfigured
{
    public function configuring(EntityPostType $postType): void
    {
        $postType->setRawArgs(['labels' => ['not_found' => 'Nothing here']]);
    }
}

#[Taxonomy('lbl_genre', singular: 'Genre', plural: 'Genres', objectType: 'book')]
final class I18nLabelsGenre {}

#[Taxonomy('lbl_themed_genre', singular: 'Genre', plural: 'Genres', objectType: 'book', textDomain: 'my-theme')]
final class I18nLabelsThemedGenre {}

/**
 * Discovers a class, runs the init callbacks and returns what WordPress was given for its labels.
 *
 * @return array<string, mixed>
 */
function i18nLabelsRegistered(string $class, string $kind = 'post_type'): array
{
    $hooks = i18nWiringInitHooks();
    $registered = i18nWiringWordPress();
    $location = new DiscoveryLocation('Tests\\', __DIR__);
    $structure = DiscoveredClass::fromReflection(new ReflectionClass($class));

    if ($kind === 'taxonomy') {
        $discovery = new TaxonomyDiscovery(new TaxonomyService(new TaxonomyFactory, new WordPressTaxonomyRegistry));
    } else {
        $discovery = new PostTypeDiscovery(new PostTypeService(new PostTypeFactory, new WordPressPostTypeRegistry));
    }

    $discovery->discover($location, $structure, new ReflectionCache);
    $discovery->apply();
    i18nWiringRunInit($hooks);

    return $registered[$kind.':'.(new ReflectionClass($class))->getAttributes()[0]->newInstance()->slug]['labels'];
}

/**
 * The labels of the entity, on the keys the default labels cover: what Extended CPTs adds on top is left out.
 *
 * @param  array<string, mixed>  $labels
 * @param  array<string, mixed>  $covering
 * @return array<string, mixed>
 */
function i18nLabelsRestrictedTo(array $labels, array $covering): array
{
    return array_intersect_key($labels, $covering);
}

describe('default labels without a catalogue', function (): void {
    it('are those of a post type with its singular and plural only', function (): void {
        i18nWiringCatalogue([]);

        $labels = i18nLabelsRegistered(I18nLabelsPlain::class);

        expect(i18nLabelsRestrictedTo($labels, i18nLabelsBookPostType()))->toBe(i18nLabelsBookPostType());
    });

    it('are those of a taxonomy', function (): void {
        i18nWiringCatalogue([]);

        $labels = i18nLabelsRegistered(I18nLabelsGenre::class, 'taxonomy');

        expect(i18nLabelsRestrictedTo($labels, i18nLabelsGenreTaxonomy()))->toEqual(i18nLabelsGenreTaxonomy());
    });

    it('give way to the labels the class declares, whichever are missing', function (): void {
        i18nWiringCatalogue([]);

        $labels = i18nLabelsRegistered(I18nLabelsPartial::class);

        expect($labels['menu_name'])->toBe('Library')
            ->and($labels['add_new'])->toBe('Create')
            ->and($labels['add_new_item'])->toBe('Add New Book')
            ->and($labels['all_items'])->toBe('All Books')
            ->and($labels)->not->toHaveKey(TranslatingEntityRegistry::DEFAULT_LABELS);
    });

    it('are not built when withArgs() replaces the labels, as before', function (): void {
        i18nWiringCatalogue([]);

        $labels = i18nLabelsRegistered(I18nLabelsReplaced::class);

        expect($labels['name'])->toBe('Only this')
            ->and($labels)->not->toHaveKey(TranslatingEntityRegistry::DEFAULT_LABELS);
    });

    it('are completed by the labels configuring() sets', function (): void {
        i18nWiringCatalogue([]);

        $labels = i18nLabelsRegistered(I18nLabelsConfigured::class);

        expect($labels['not_found'])->toBe('Nothing here')
            ->and($labels['edit_item'])->toBe('Edit Book');
    });

    it('use the templates of the text domain of the entity', function (): void {
        i18nWiringCatalogue([]);

        i18nLabelsRegistered(I18nLabelsThemed::class);

        expect(array_unique(array_map(fn (string $call): string => explode('|', $call)[0], i18nWiringCalls())))->toBe(['my-theme']);
    });

    it('are built by the registry itself for the exact arrays the discovery gives', function (): void {
        i18nWiringCatalogue([]);
        $recorder = i18nWiringRecorder();

        $registry = new TranslatingEntityRegistry($recorder);
        $registry->register('book', ['labels' => TranslatingEntityRegistry::defaultLabels('post_type', 'Book', 'Books'), 'text_domain' => 'pollora'], ['singular' => 'Book', 'plural' => 'Books']);

        $postType = $recorder->registered;
        $registry->register('genre', ['labels' => TranslatingEntityRegistry::defaultLabels('taxonomy', 'Genre', 'Genres'), 'text_domain' => 'pollora'], ['singular' => 'Genre', 'plural' => 'Genres']);

        expect($postType['args']['labels'])->toBe(i18nLabelsBookPostType())
            ->and($recorder->registered['args']['labels'])->toBe(i18nLabelsGenreTaxonomy())
            ->and($recorder->registered['names'])->toBe(['singular' => 'Genre', 'plural' => 'Genres']);
    });
});

describe('default labels with a catalogue', function (): void {
    it('translate the names and the templates on init, and not at boot', function (): void {
        i18nWiringCatalogue(['my-theme|Book' => 'Livre', 'my-theme|Books' => 'Livres', 'my-theme|Add New %s' => 'Ajouter un nouveau %s', 'my-theme|All %s' => 'Tous les %s']);
        $hooks = i18nWiringInitHooks();
        $registered = i18nWiringWordPress();

        $discovery = new PostTypeDiscovery(new PostTypeService(new PostTypeFactory, new WordPressPostTypeRegistry));
        $discovery->discover(new DiscoveryLocation('Tests\\', __DIR__), DiscoveredClass::fromReflection(new ReflectionClass(I18nLabelsThemed::class)), new ReflectionCache);
        $discovery->apply();

        expect(i18nWiringCalls())->toBe([]);

        i18nWiringRunInit($hooks);
        $labels = $registered['post_type:lbl_themed']['labels'];

        expect($labels['name'])->toBe('Livres')
            ->and($labels['singular_name'])->toBe('Livre')
            ->and($labels['add_new_item'])->toBe('Ajouter un nouveau Livre')
            ->and($labels['all_items'])->toBe('Tous les Livres')
            ->and($labels['edit_item'])->toBe('Edit Livre');
    });

    it('translate the names of a taxonomy, in pollora unless the attribute names a domain', function (): void {
        i18nWiringCatalogue(['pollora|Genre' => 'Genre litteraire', 'my-theme|Genre' => 'Style']);

        $default = i18nLabelsRegistered(I18nLabelsGenre::class, 'taxonomy');
        $themed = i18nLabelsRegistered(I18nLabelsThemedGenre::class, 'taxonomy');

        expect($default['singular_name'])->toBe('Genre litteraire')
            ->and($default['edit_item'])->toBe('Edit Genre litteraire')
            ->and($themed['singular_name'])->toBe('Style');
    });

    it('translate the names handed to WordPress alongside the labels', function (): void {
        i18nWiringCatalogue(['pollora|Book' => 'Livre', 'pollora|Books' => 'Livres']);
        $recorder = i18nWiringRecorder();

        (new TranslatingEntityRegistry($recorder))->register('book', ['labels' => TranslatingEntityRegistry::defaultLabels('post_type', 'Book', 'Books')], ['singular' => 'Book', 'plural' => 'Books', 'slug' => 'livres']);

        expect($recorder->registered['names'])->toBe(['singular' => 'Livre', 'plural' => 'Livres', 'slug' => 'livres']);
    });

    it('leave a name another hand changed as it is', function (): void {
        i18nWiringCatalogue(['pollora|Book' => 'Livre']);
        $recorder = i18nWiringRecorder();

        (new TranslatingEntityRegistry($recorder))->register('book', ['labels' => TranslatingEntityRegistry::defaultLabels('post_type', 'Book', 'Books')], ['singular' => 'Volume', 'plural' => 'Books']);

        expect($recorder->registered['names']['singular'])->toBe('Volume');
    });

    it('resolve a Translatable in the labels the class declares', function (): void {
        i18nWiringCatalogue(['pollora|Library' => 'Bibliotheque']);
        $recorder = i18nWiringRecorder();

        (new TranslatingEntityRegistry($recorder))->register('book', [
            'labels' => [...TranslatingEntityRegistry::defaultLabels('post_type', 'Book', 'Books'), 'menu_name' => new Translatable('Library')],
        ], []);

        expect($recorder->registered['args']['labels']['menu_name'])->toBe('Bibliotheque');
    });

    it('do not look the names up in a domain with no catalogue, and still ask for the templates', function (): void {
        i18nWiringCatalogue([]);
        Functions\when('get_translations_for_domain')->justReturn(new NOOP_Translations);
        Functions\when('has_filter')->justReturn(false);

        $recorder = i18nWiringRecorder();

        (new TranslatingEntityRegistry($recorder))->register('book', ['labels' => TranslatingEntityRegistry::defaultLabels('post_type', 'Book', 'Books'), 'text_domain' => 'my-theme'], ['singular' => 'Book', 'plural' => 'Books']);

        expect(i18nWiringCalls())->not->toContain('my-theme|Book')
            ->and(i18nWiringCalls())->not->toContain('my-theme|Books')
            ->and(i18nWiringCalls())->toContain('my-theme|Add New %s')
            ->and($recorder->registered['args']['labels'])->toBe(i18nLabelsBookPostType())
            ->and($recorder->registered['names'])->toBe(['singular' => 'Book', 'plural' => 'Books']);
    });

    it('look the names up in a domain with no catalogue when a plugin translates through the gettext filter', function (): void {
        i18nWiringCatalogue([]);
        Functions\when('get_translations_for_domain')->justReturn(new NOOP_Translations);
        Functions\when('has_filter')->justReturn(10);

        (new TranslatingEntityRegistry(i18nWiringRecorder()))->register('book', ['labels' => TranslatingEntityRegistry::defaultLabels('post_type', 'Book', 'Books'), 'text_domain' => 'my-theme'], ['singular' => 'Book', 'plural' => 'Books']);

        expect(i18nWiringCalls())->toContain('my-theme|Book')->toContain('my-theme|Books');
    });

    it('look each name up once, and no template more than the discovery did', function (): void {
        i18nWiringCatalogue([]);
        $recorder = i18nWiringRecorder();

        (new TranslatingEntityRegistry($recorder))->register('book', ['labels' => TranslatingEntityRegistry::defaultLabels('post_type', 'Book', 'Books')], ['singular' => 'Book', 'plural' => 'Books']);
        $calls = i18nWiringCalls();

        expect(array_count_values($calls)['pollora|Book'])->toBe(1)
            ->and(array_count_values($calls)['pollora|Books'])->toBe(1)
            ->and($calls)->toHaveCount(2 + 15);
    });
});
