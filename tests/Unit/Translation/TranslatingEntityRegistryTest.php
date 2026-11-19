<?php

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Pollora\Foundation\Providers\ArtisanServiceProvider;
use Pollora\I18n\Runtime\Translatable;
use Pollora\I18n\Runtime\TranslatablePlural;
use Pollora\PostType\Infrastructure\Factories\PostTypeFactory;
use Pollora\Taxonomy\Infrastructure\Factories\TaxonomyFactory;
use Pollora\Translation\Infrastructure\Adapters\TranslatingEntityRegistry;

require_once __DIR__.'/Support/i18nWiring.php';

describe('TranslatingEntityRegistry', function (): void {
    it('resolves the texts nested in the arguments before the registry gets them', function (): void {
        i18nWiringCatalogue(['my-theme|Written by' => 'Écrit par', 'my-theme|stars|Rating' => 'Note']);
        $recorder = i18nWiringRecorder();

        (new TranslatingEntityRegistry($recorder))->register('book', [
            'text_domain' => 'my-theme',
            'admin_cols' => ['author' => ['title' => new Translatable('Written by')], 'rating' => ['title' => new Translatable('Rating', context: 'stars')]],
        ], ['singular' => 'Book']);

        expect($recorder->registered['args']['admin_cols'])->toBe([
            'author' => ['title' => 'Écrit par'],
            'rating' => ['title' => 'Note'],
        ])->and($recorder->registered['names'])->toBe(['singular' => 'Book']);
    });

    it('resolves the texts of the names too', function (): void {
        i18nWiringCatalogue(['my-theme|Book' => 'Livre']);
        $recorder = i18nWiringRecorder();

        (new TranslatingEntityRegistry($recorder))->register('book', ['text_domain' => 'my-theme'], ['singular' => new Translatable('Book')]);

        expect($recorder->registered['names'])->toBe(['singular' => 'Livre']);
    });

    it('turns a plural text into the structure of _n_noop() with the domain of the entity', function (): void {
        i18nWiringCatalogue([]);
        $recorder = i18nWiringRecorder();

        (new TranslatingEntityRegistry($recorder))->register('book', ['text_domain' => 'my-theme', 'cols' => ['loans' => new TranslatablePlural('One loan', '%d loans')]], []);

        expect($recorder->registered['args']['cols']['loans'])->toMatchArray([
            'singular' => 'One loan',
            'plural' => '%d loans',
            'context' => null,
            'domain' => 'my-theme',
        ]);
    });

    it('translates in the text domain of the entity, then in pollora, unless the text names its own', function (): void {
        i18nWiringCatalogue(['my-theme|Hello' => 'Bonjour thème', 'pollora|Hello' => 'Bonjour pollora', 'other|Hello' => 'Bonjour autre']);
        $recorder = i18nWiringRecorder();
        $registry = new TranslatingEntityRegistry($recorder);
        $text = fn (?string $domain = null): array => ['title' => new Translatable('Hello', domain: $domain)];

        $registry->register('a', ['text_domain' => 'my-theme', ...$text()], []);
        $fromEntity = $recorder->registered['args']['title'];
        $registry->register('b', $text(), []);
        $fromDefault = $recorder->registered['args']['title'];
        $registry->register('c', ['text_domain' => '', ...$text()], []);
        $fromEmpty = $recorder->registered['args']['title'];
        $registry->register('d', ['text_domain' => 'my-theme', ...$text('other')], []);
        $explicit = $recorder->registered['args']['title'];

        expect([$fromEntity, $fromDefault, $fromEmpty, $explicit])->toBe(['Bonjour thème', 'Bonjour pollora', 'Bonjour pollora', 'Bonjour autre']);
    });

    it('hands over arguments without a Translatable exactly as they were, and asks no catalogue', function (): void {
        i18nWiringCatalogue([]);
        $recorder = i18nWiringRecorder();
        $args = ['labels' => ['name' => 'Books', 'add_new_item' => 'Add New Book'], 'text_domain' => 'my-theme', 'supports' => ['title'], 'public' => true, 'rewrite' => ['slug' => 'livres']];
        $names = ['singular' => 'Book', 'plural' => 'Books'];

        (new TranslatingEntityRegistry($recorder))->register('book', $args, $names);

        expect($recorder->registered)->toBe(['slug' => 'book', 'args' => $args, 'names' => $names])
            ->and(i18nWiringCalls())->toBe([]);
    });
});

describe('registration through the factories, the discovery and configuring()', function (): void {
    it('translates a post type argument on init and not before', function (): void {
        i18nWiringCatalogue(['my-theme|Written by' => 'Écrit par']);
        $hooks = i18nWiringInitHooks();
        $registered = i18nWiringWordPress();

        (new PostTypeFactory)->make('book', 'Book', 'Books', ['text_domain' => 'my-theme', 'admin_cols' => ['author' => ['title' => new Translatable('Written by')]]]);

        expect(i18nWiringCalls())->toBe([])->and($registered->count())->toBe(0)->and($hooks)->toHaveCount(1);

        i18nWiringRunInit($hooks);

        expect($registered['post_type:book']['admin_cols'])->toBe(['author' => ['title' => 'Écrit par']])
            ->and(i18nWiringCalls())->toBe(['my-theme|Written by']);
    });

    it('translates a taxonomy argument on init and not before', function (): void {
        i18nWiringCatalogue(['pollora|Genre filter' => 'Filtre de genre']);
        $hooks = i18nWiringInitHooks();
        $registered = i18nWiringWordPress();

        (new TaxonomyFactory)->make('genre', 'book', 'Genre', 'Genres', ['admin_filter' => ['title' => new Translatable('Genre filter')]]);

        expect(i18nWiringCalls())->toBe([])->and($registered->count())->toBe(0);

        i18nWiringRunInit($hooks);

        expect($registered['taxonomy:genre']['admin_filter'])->toBe(['title' => 'Filtre de genre']);
    });

    it('translates what the attributes of a discovered class declare, with the textDomain of the attribute', function (): void {
        i18nWiringCatalogue([
            'my-theme|Written by' => 'Écrit par',
            'my-theme|stars|Rating' => 'Note',
            'other-plugin|Copies' => 'Exemplaires',
        ]);
        $hooks = i18nWiringInitHooks();
        $registered = i18nWiringWordPress();

        i18nWiringDiscover(I18nWiringBook::class);
        $before = i18nWiringCalls();
        i18nWiringRunInit($hooks);
        $columns = $registered['post_type:i18n_book']['admin_cols'];

        expect($columns['isbn'])->toBe(['title' => 'ISBN'])
            ->and($columns['author'])->toBe(['title' => 'Écrit par'])
            ->and($columns['rating'])->toBe(['title' => 'Note', 'meta_key' => 'rating'])
            ->and($columns['copies'])->toBe(['title' => 'Exemplaires'])
            ->and($columns['loans']['title'])->toMatchArray(['singular' => 'One loan', 'plural' => '%d loans', 'domain' => 'my-theme'])
            ->and($before)->not->toContain('my-theme|Written by')
            ->and(i18nWiringCalls())->toContain('my-theme|Written by');
    });

    it('translates what a configuring() method sets, with the textDomain of the attribute', function (): void {
        i18nWiringCatalogue(['my-theme|Written by' => 'Écrit par']);
        $hooks = i18nWiringInitHooks();
        $registered = i18nWiringWordPress();

        i18nWiringDiscover(I18nWiringConfigured::class);
        i18nWiringRunInit($hooks);

        expect($registered['post_type:i18n_configured']['admin_cols'])->toBe(['author' => ['title' => 'Écrit par']]);
    });

    it('keeps the generated labels as the discovery builds them, translated or not', function (): void {
        i18nWiringCatalogue(['pollora|Add New %s' => 'Ajouter %s']);
        $hooks = i18nWiringInitHooks();
        $registered = i18nWiringWordPress();

        i18nWiringDiscover(I18nWiringPlain::class);
        i18nWiringRunInit($hooks);
        $args = $registered['post_type:i18n_plain'];

        expect($args['labels'])->toMatchArray([
            'name' => 'Plains',
            'singular_name' => 'Plain',
            'add_new' => 'Add New',
            'add_new_item' => 'Ajouter Plain',
            'edit_item' => 'Edit Plain',
            'new_item' => 'New Plain',
            'view_item' => 'View Plain',
            'view_items' => 'View Plains',
            'search_items' => 'Search Plains',
            'not_found' => 'No Plains found',
            'not_found_in_trash' => 'No Plains found in Trash',
            'parent_item_colon' => 'Parent Plain:',
            'all_items' => 'All Plains',
            'archives' => 'Plain Archives',
            'attributes' => 'Plain Attributes',
            'insert_into_item' => 'Insert into Plain',
            'uploaded_to_this_item' => 'Uploaded to this Plain',
        ])->and($args['admin_cols'])->toBe(['isbn' => ['title' => 'ISBN']])
            ->and($args['text_domain'])->toBe('pollora');
    });
});

describe('what a web request loads', function (): void {
    /**
     * Runs a script in a process of its own, like a request, and returns what it printed.
     */
    $request = function (string $script): string {
        $autoload = dirname(__DIR__, 3).'/vendor/autoload.php';
        $code = 'require '.var_export($autoload, true).'; '.$script;
        $output = shell_exec(escapeshellarg(PHP_BINARY).' -d display_errors=1 -r '.escapeshellarg($code).' 2>&1');

        $lines = array_filter(explode("\n", trim((string) $output)), fn (string $line): bool => ! str_contains($line, 'JIT is incompatible'));

        return trim(implode("\n", $lines));
    };

    it('loads the translator and the registry and nothing from the tooling when it registers an entity', function () use ($request): void {
        $output = $request(<<<'PHP'
            $port = new class implements Pollora\Entity\Port\Out\EntityRegistryPort { public function register(string $slug, array $args, array $names): void {} };
            (new Pollora\Translation\Infrastructure\Adapters\TranslatingEntityRegistry($port))->register('book', ['labels' => ['name' => 'Books'], 'text_domain' => 'x'], ['singular' => 'Book']);
            $loaded = array_values(array_filter(get_declared_classes(), fn ($class) => str_starts_with($class, 'Pollora\\I18n\\') || str_starts_with($class, 'Symfony\\Component\\Console\\') || str_starts_with($class, 'PhpParser\\')));
            echo json_encode($loaded);
            PHP);

        expect($output)->toBe('["Pollora\\\\I18n\\\\Runtime\\\\Translator"]');
    });

    it('does not load the tooling when the service providers register, outside the console', function () use ($request): void {
        $output = $request(<<<'PHP'
            putenv('APP_RUNNING_IN_CONSOLE=false');
            $app = new Illuminate\Foundation\Application(sys_get_temp_dir());
            (new Pollora\Foundation\Providers\ArtisanServiceProvider($app))->register();
            echo json_encode([$app->runningInConsole(), count(array_filter(get_declared_classes(), fn ($class) => str_starts_with($class, 'Pollora\\I18n\\')))]);
            PHP);

        expect($output)->toBe('[false,0]');
    });

    it('registers the commands of the package under their pollora names only, when Artisan starts', function (): void {
        $app = new Application(sys_get_temp_dir());
        $app->instance('files', new Filesystem);
        (new ArtisanServiceProvider($app))->register();

        $artisan = new Illuminate\Console\Application($app, new Dispatcher($app), '1.0');
        Illuminate\Console\Application::forgetBootstrappers();

        expect($artisan->has('pollora:i18n:extract'))->toBeTrue()
            ->and($artisan->has('pollora:i18n:update'))->toBeTrue()
            ->and($artisan->has('pollora:i18n:compile'))->toBeTrue()
            ->and($artisan->has('extract'))->toBeFalse()
            ->and($artisan->has('update'))->toBeFalse()
            ->and($artisan->has('compile'))->toBeFalse();
    });
});
