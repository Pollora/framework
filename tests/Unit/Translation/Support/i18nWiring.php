<?php

declare(strict_types=1);

/*
 * The stand-ins for WordPress's translation catalogues and for the registries that `TranslatingEntityRegistryTest` and
 * `DefaultLabelsTest` both use. They live here, loaded with `require_once` by each of the two files, so that either
 * file passes alone, or in a process of its own when the suite runs in parallel or under mutation testing: a function
 * defined in one test file is only there for a test that runs in the same process after that file was loaded.
 */

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Pollora\Attributes\PostType;
use Pollora\Attributes\PostType\AdminCols;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\Entity\Domain\Model\PostType as EntityPostType;
use Pollora\Entity\Port\Out\EntityRegistryPort;
use Pollora\I18n\Runtime\Translatable;
use Pollora\I18n\Runtime\TranslatablePlural;
use Pollora\PostType\Application\Services\PostTypeService;
use Pollora\PostType\Infrastructure\Adapters\WordPressPostTypeRegistry;
use Pollora\PostType\Infrastructure\Factories\PostTypeFactory;
use Pollora\PostType\Infrastructure\Services\PostTypeDiscovery;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;

/**
 * Stands for the catalogues of WordPress: a French word for each string asked, and a log of what was asked.
 */
$GLOBALS['i18nWiring'] = ['calls' => [], 'words' => []];

/**
 * @param  array<string, string>  $words  'domain|text' or 'domain|context|text' => translation
 */
function i18nWiringCatalogue(array $words): void
{
    $GLOBALS['i18nWiring'] = ['calls' => [], 'words' => $words, 'filters' => []];

    $lookup = function (string $key, string $text): string {
        $GLOBALS['i18nWiring']['calls'][] = $key;

        return i18nWiringFiltered($GLOBALS['i18nWiring']['words'][$key] ?? $text, $text, explode('|', $key)[0]);
    };

    Functions\when('get_locale')->justReturn('fr_FR');
    defined('WP_LANG_DIR') || define('WP_LANG_DIR', '/tmp/wp-lang');
    // The catalogue of the framework counts as loaded: the registry does not try to load it.
    $GLOBALS['l10n']['pollora'] = new stdClass;
    Functions\when('translate')->alias(fn (string $text, string $domain = 'default'): string => $lookup($domain.'|'.$text, $text));
    Functions\when('_x')->alias(fn (string $text, string $context, string $domain = 'default'): string => $lookup($domain.'|'.$context.'|'.$text, $text));
    Container::getInstance()->instance('translator', new class
    {
        public function get(string $key, mixed $replace = [], ?string $locale = null): string
        {
            $domain = is_string($replace) ? $replace : 'default';
            $GLOBALS['i18nWiring']['calls'][] = $domain.'|'.$key;

            return i18nWiringFiltered($GLOBALS['i18nWiring']['words'][$domain.'|'.$key] ?? $key, $key, $domain);
        }
    });
}

/**
 * What WordPress does after the catalogue answered: the `gettext` filters, then the `gettext_{domain}` ones.
 *
 * @param  array<string, callable>  $filters  Added by a test: 'gettext' or 'gettext_my-theme' => fn (string $translation, string $text, string $domain): string
 */
function i18nWiringFiltered(string $translation, string $text, string $domain): string
{
    foreach (['gettext', 'gettext_'.$domain] as $hook) {
        if (isset($GLOBALS['i18nWiring']['filters'][$hook])) {
            $translation = $GLOBALS['i18nWiring']['filters'][$hook]($translation, $text, $domain);
        }
    }

    return $translation;
}

/**
 * @return list<string> What was asked of the catalogues since i18nWiringCatalogue()
 */
function i18nWiringCalls(): array
{
    return $GLOBALS['i18nWiring']['calls'];
}

/**
 * The registry the entity registration service talks to, which keeps what it was given.
 */
function i18nWiringRecorder(): EntityRegistryPort
{
    return new class implements EntityRegistryPort
    {
        /** @var array{slug: string, args: array<string, mixed>, names: array<string, mixed>}|null */
        public ?array $registered = null;

        public function register(string $slug, array $args, array $names): void
        {
            $this->registered = ['slug' => $slug, 'args' => $args, 'names' => $names];
        }
    };
}

/**
 * Captures the callbacks the entities hook on `init`, to run them when WordPress would.
 *
 * @return ArrayObject<int, callable>
 */
function i18nWiringInitHooks(): ArrayObject
{
    /** @var ArrayObject<int, callable> $hooks */
    $hooks = new ArrayObject;

    Functions\when('add_action')->alias(function (string $hook, callable $callback) use ($hooks): bool {
        if ($hook === 'init') {
            $hooks[] = $callback;
        }

        return true;
    });

    return $hooks;
}

/**
 * Captures what reaches register_post_type() and register_taxonomy(), behind Extended CPTs.
 *
 * The library is installed with the framework and is what the entity adapters call first,
 * so it runs here against stubs of the WordPress functions it reads.
 *
 * @return ArrayObject<string, array<string, mixed>>
 */
function i18nWiringWordPress(): ArrayObject
{
    /** @var ArrayObject<string, array<string, mixed>> $registered */
    $registered = new ArrayObject;

    Functions\when('get_post_type_object')->justReturn();
    Functions\when('get_taxonomy')->justReturn();
    Functions\stubs([
        'get_taxonomies' => [],
        'get_post_types' => [],
        'post_type_exists' => false,
        'taxonomy_exists' => false,
        'is_admin' => false,
        'doing_action' => true,
        'did_action' => 1,
    ]);
    Functions\when('register_post_type')->alias(function (string $slug, array $args) use ($registered): object {
        $registered['post_type:'.$slug] = $args;

        return new stdClass;
    });
    Functions\when('register_taxonomy')->alias(function (string $slug, mixed $objectType, array $args) use ($registered): object {
        $registered['taxonomy:'.$slug] = $args;

        return new stdClass;
    });

    return $registered;
}

#[PostType('i18n_book', singular: 'Book', plural: 'Books', textDomain: 'my-theme')]
#[AdminCols([
    'isbn' => ['title' => 'ISBN'],
    'author' => ['title' => new Translatable('Written by')],
    'rating' => ['title' => new Translatable('Rating', context: 'stars'), 'meta_key' => 'rating'],
    'copies' => ['title' => new Translatable('Copies', domain: 'other-plugin')],
    'loans' => ['title' => new TranslatablePlural('One loan', '%d loans', comment: 'Column header')],
])]
final class I18nWiringBook {}

#[PostType('i18n_plain', singular: 'Plain', plural: 'Plains')]
#[AdminCols(['isbn' => ['title' => 'ISBN']])]
final class I18nWiringPlain {}

#[PostType('i18n_configured', singular: 'Configured', plural: 'Configureds', textDomain: 'my-theme')]
final class I18nWiringConfigured
{
    public function configuring(EntityPostType $postType): void
    {
        $postType->setRawArgs(['admin_cols' => ['author' => ['title' => new Translatable('Written by')]]]);
    }
}

/**
 * Discovers a post type class and registers it through the real service, factory and registration service.
 */
function i18nWiringDiscover(string $class): void
{
    $discovery = new PostTypeDiscovery(new PostTypeService(new PostTypeFactory, new WordPressPostTypeRegistry));
    $discovery->discover(new DiscoveryLocation('Tests\\', __DIR__), DiscoveredClass::fromReflection(new ReflectionClass($class)), new ReflectionCache);
    $discovery->apply();
}

/**
 * @param  ArrayObject<int, callable>  $hooks
 */
function i18nWiringRunInit(ArrayObject $hooks): void
{
    foreach ($hooks as $callback) {
        $callback();
    }
}
