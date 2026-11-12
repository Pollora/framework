<?php

declare(strict_types=1);

namespace Pollora\Translation\Infrastructure\Adapters;

use Pollora\Entity\Port\Out\EntityRegistryPort;
use Pollora\I18n\Runtime\Translator;

/**
 * Translates an entity just before WordPress registers it.
 *
 * The entity registration service calls its registry from the callback of the
 * `init` action, never earlier, so the text domains of themes and plugins are
 * loaded by then. This is the one place where the framework translates what a
 * post type or a taxonomy declares:
 *
 * - the Translatable and TranslatablePlural of its arguments, through Translator::resolve();
 * - for the classes the discovery finds, the texts the attributes write in the registration
 *   arguments (the labels, `label`, `description`, `title_placeholder`, `featured_image` and
 *   the titles of the admin columns), each one looked up in the text domain of the entity;
 * - for the same classes, the singular and plural names, and the default labels built from
 *   them. A template ("Add New %s") belongs to the framework, so it is always looked up in the
 *   `pollora` domain, then given the name translated in the domain of the entity.
 *
 * The discovery does not build labels: it asks for them with defaultLabels(), as the
 * only entry of the `labels` argument. Labels written by the class (#[Labels],
 * withArgs(), configuring()) are merged over the default ones, as they always were; a
 * class whose `labels` replace that entry gets none and no text of it is looked up. An
 * entity that did not ask for default labels is registered with the arguments it has, resolved.
 *
 * A text with no translation comes back as it was, so a label written directly in the
 * language of the site stays. Nothing is looked up when nothing could translate it.
 */
final readonly class TranslatingEntityRegistry implements EntityRegistryPort
{
    public const string DEFAULT_DOMAIN = 'pollora';

    public const string POST_TYPE = 'post_type';

    public const string TAXONOMY = 'taxonomy';

    /** The entry of the `labels` argument that asks for the default labels. */
    public const string DEFAULT_LABELS = '_pollora_default_labels';

    /**
     * The registration arguments, besides `labels` and the titles of `admin_cols`, whose text an attribute writes.
     *
     * @var list<string>
     */
    public const array TRANSLATED_ARGUMENTS = ['label', 'description', 'title_placeholder', 'featured_image'];

    public function __construct(private EntityRegistryPort $registry) {}

    /**
     * What the discovery puts in the `labels` argument to have the default labels built on init.
     *
     * @param  self::POST_TYPE|self::TAXONOMY  $kind
     * @return array<string, array{string, string, string}>
     */
    public static function defaultLabels(string $kind, string $singular, string $plural): array
    {
        return [self::DEFAULT_LABELS => [$kind, $singular, $plural]];
    }

    /**
     * Loads the catalogue of the framework, the `pollora` domain, unless it is loaded.
     *
     * The service provider loads it once WordPress is up, which is after `init` on a web request,
     * and the default labels are built on `init`: they ask for it first. Translations written by the
     * site in `WP_LANG_DIR/pollora/` win over the ones bundled with the framework.
     */
    public static function loadFrameworkTextDomain(): void
    {
        if (! function_exists('load_textdomain') || ! function_exists('determine_locale')) {
            return;
        }

        $loaded = $GLOBALS['l10n'][self::DEFAULT_DOMAIN] ?? null;

        if ($loaded !== null && ! is_a($loaded, 'NOOP_Translations')) {
            return;
        }

        $locale = determine_locale();

        // Once per locale and request: a site in its source language has no file to find.
        static $attempted = [];

        if (isset($attempted[$locale])) {
            return;
        }

        $attempted[$locale] = true;
        $moFile = sprintf('pollora-%s.mo', $locale);

        if (! load_textdomain(self::DEFAULT_DOMAIN, WP_LANG_DIR.'/pollora/'.$moFile)) {
            load_textdomain(self::DEFAULT_DOMAIN, dirname(__DIR__, 4).'/resources/languages/'.$moFile);
        }
    }

    /**
     * The text domain of a Translatable that names none, and of the texts of the entity,
     * is the `text_domain` of the entity (the `textDomain` of its attribute), else `pollora`.
     *
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $names
     */
    public function register(string $slug, array $args, array $names): void
    {
        $textDomain = $args['text_domain'] ?? null;
        $domain = is_string($textDomain) && $textDomain !== '' ? $textDomain : self::DEFAULT_DOMAIN;

        if (isset($args['labels'][self::DEFAULT_LABELS])) {
            [$args, $names] = $this->withTranslatedTexts($args, $names, $domain);
        }

        $this->registry->register(
            $slug,
            Translator::resolve($args, $domain),
            Translator::resolve($names, $domain),
        );
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $names
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function withTranslatedTexts(array $args, array $names, string $domain): array
    {
        self::loadFrameworkTextDomain();

        $labels = $args['labels'];
        [$kind, $rawSingular, $rawPlural] = $labels[self::DEFAULT_LABELS];
        unset($labels[self::DEFAULT_LABELS]);

        $translating = ! $this->hasNothingToLookUp($domain);
        $singular = $translating ? $this->translateText($rawSingular, $domain) : $rawSingular;
        $plural = $translating ? $this->translateText($rawPlural, $domain) : $rawPlural;

        if ($translating) {
            [$args, $labels] = $this->withTranslatedExplicitTexts($args, $labels, $domain);
        }

        $args['labels'] = array_merge(
            $kind === self::TAXONOMY
                ? $this->taxonomyLabels($singular, $plural)
                : $this->postTypeLabels($singular, $plural),
            $labels,
        );

        // A name another hand changed (configuring()) is left as it is.
        if (($names['singular'] ?? null) === $rawSingular) {
            $names['singular'] = $singular;
        }

        if (($names['plural'] ?? null) === $rawPlural) {
            $names['plural'] = $plural;
        }

        return [$args, $names];
    }

    /**
     * The texts an attribute wrote, which are in the language of the code: each is looked up once, in the domain of the entity.
     *
     * @param  array<string, mixed>  $args
     * @param  array<array-key, mixed>  $labels  The labels other than the default ones
     * @return array{array<string, mixed>, array<array-key, mixed>}
     */
    private function withTranslatedExplicitTexts(array $args, array $labels, string $domain): array
    {
        foreach ($labels as $key => $label) {
            if (is_string($label)) {
                $labels[$key] = $this->translateText($label, $domain);
            }
        }

        foreach (self::TRANSLATED_ARGUMENTS as $key) {
            if (isset($args[$key]) && is_string($args[$key])) {
                $args[$key] = $this->translateText($args[$key], $domain);
            }
        }

        if (isset($args['admin_cols']) && is_array($args['admin_cols'])) {
            foreach ($args['admin_cols'] as $column => $configuration) {
                if (is_array($configuration) && isset($configuration['title']) && is_string($configuration['title'])) {
                    $args['admin_cols'][$column]['title'] = $this->translateText($configuration['title'], $domain);
                }
            }
        }

        return [$args, $labels];
    }

    /**
     * Whether __() would give the text back: no catalogue for the domain and nothing on the `gettext` filter.
     *
     * Reads what WordPress keeps in memory. A domain with no catalogue is a NOOP_Translations
     * (the site in its source language, or a theme with no file for the locale), and stays one
     * after the first lookup, where is_textdomain_loaded() would say true. A plugin that
     * translates through the `gettext` filter still gets its lookups.
     */
    private function hasNothingToLookUp(string $domain): bool
    {
        return function_exists('get_translations_for_domain')
            && is_a(get_translations_for_domain($domain), 'NOOP_Translations')
            && ! has_filter('gettext');
    }

    /**
     * A text written in the source language, translated in the given text domain.
     */
    private function translateText(string $text, string $domain): string
    {
        return $text === '' || ! function_exists('__') ? $text : __($text, $domain);
    }

    /**
     * The default labels of a post type, from its singular and plural names.
     *
     * The templates are literal __() calls in the `pollora` domain, whatever the domain of the entity,
     * so that `wp i18n make-pot` finds them; the names given to sprintf() are the translated ones.
     *
     * @param  string  $singular  The singular name
     * @param  string  $plural  The plural name
     * @return array<string, string> The labels array
     */
    private function postTypeLabels(string $singular, string $plural): array
    {
        return [
            'name' => $plural,
            'singular_name' => $singular,
            /* translators: %s: post type general name (plural) */
            'add_new' => __('Add New', 'pollora'),
            /* translators: %s: post type singular name */
            'add_new_item' => sprintf(__('Add New %s', 'pollora'), $singular),
            /* translators: %s: post type singular name */
            'edit_item' => sprintf(__('Edit %s', 'pollora'), $singular),
            /* translators: %s: post type singular name */
            'new_item' => sprintf(__('New %s', 'pollora'), $singular),
            /* translators: %s: post type singular name */
            'view_item' => sprintf(__('View %s', 'pollora'), $singular),
            /* translators: %s: post type general name (plural) */
            'view_items' => sprintf(__('View %s', 'pollora'), $plural),
            /* translators: %s: post type general name (plural) */
            'search_items' => sprintf(__('Search %s', 'pollora'), $plural),
            /* translators: %s: post type general name (plural) */
            'not_found' => sprintf(__('No %s found', 'pollora'), $plural),
            /* translators: %s: post type general name (plural) */
            'not_found_in_trash' => sprintf(__('No %s found in Trash', 'pollora'), $plural),
            /* translators: %s: post type singular name */
            'parent_item_colon' => sprintf(__('Parent %s:', 'pollora'), $singular),
            /* translators: %s: post type general name (plural) */
            'all_items' => sprintf(__('All %s', 'pollora'), $plural),
            /* translators: %s: post type singular name */
            'archives' => sprintf(__('%s Archives', 'pollora'), $singular),
            /* translators: %s: post type singular name */
            'attributes' => sprintf(__('%s Attributes', 'pollora'), $singular),
            /* translators: %s: post type singular name */
            'insert_into_item' => sprintf(__('Insert into %s', 'pollora'), $singular),
            /* translators: %s: post type singular name */
            'uploaded_to_this_item' => sprintf(__('Uploaded to this %s', 'pollora'), $singular),
        ];
    }

    /**
     * The default labels of a taxonomy, from its singular and plural names.
     *
     * The templates are literal __() calls in the `pollora` domain, whatever the domain of the entity,
     * so that `wp i18n make-pot` finds them; the names given to sprintf() are the translated ones.
     *
     * @param  string  $singular  The singular name
     * @param  string  $plural  The plural name
     * @return array<string, string> The labels array
     */
    private function taxonomyLabels(string $singular, string $plural): array
    {
        return [
            'name' => $plural,
            'singular_name' => $singular,
            'menu_name' => $plural,
            /* translators: %s: taxonomy general name (plural) */
            'all_items' => sprintf(__('All %s', 'pollora'), $plural),
            /* translators: %s: taxonomy singular name */
            'edit_item' => sprintf(__('Edit %s', 'pollora'), $singular),
            /* translators: %s: taxonomy singular name */
            'view_item' => sprintf(__('View %s', 'pollora'), $singular),
            /* translators: %s: taxonomy singular name */
            'update_item' => sprintf(__('Update %s', 'pollora'), $singular),
            /* translators: %s: taxonomy singular name */
            'add_new_item' => sprintf(__('Add New %s', 'pollora'), $singular),
            /* translators: %s: taxonomy singular name */
            'new_item_name' => sprintf(__('New %s Name', 'pollora'), $singular),
            /* translators: %s: taxonomy general name (plural) */
            'search_items' => sprintf(__('Search %s', 'pollora'), $plural),
            /* translators: %s: taxonomy general name (plural) */
            'popular_items' => sprintf(__('Popular %s', 'pollora'), $plural),
            /* translators: %s: taxonomy general name (plural) */
            'separate_items_with_commas' => sprintf(__('Separate %s with commas', 'pollora'), $plural),
            /* translators: %s: taxonomy general name (plural) */
            'add_or_remove_items' => sprintf(__('Add or remove %s', 'pollora'), $plural),
            /* translators: %s: taxonomy general name (plural) */
            'choose_from_most_used' => sprintf(__('Choose from the most used %s', 'pollora'), $plural),
            /* translators: %s: taxonomy general name (plural) */
            'not_found' => sprintf(__('No %s found', 'pollora'), $plural),
            /* translators: %s: taxonomy singular name */
            'parent_item' => sprintf(__('Parent %s', 'pollora'), $singular),
            /* translators: %s: taxonomy singular name */
            'parent_item_colon' => sprintf(__('Parent %s:', 'pollora'), $singular),
        ];
    }
}
