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
 * - for the classes the discovery finds, the singular and plural names, and the default
 *   labels built from them ("Add New %s" is translated first, then given the translated name).
 *
 * The discovery does not build labels: it asks for them with defaultLabels(), as the
 * only entry of the `labels` argument. Labels written by the class (#[Labels],
 * withArgs(), configuring()) are merged over the default ones, as they always were; a
 * class whose `labels` replace that entry gets none. An entity that did not ask for
 * default labels is registered with the arguments it has, resolved.
 *
 * An argument without a Translatable or a TranslatablePlural comes back as it
 * was, and the names are not looked up when nothing could translate them.
 */
final readonly class TranslatingEntityRegistry implements EntityRegistryPort
{
    public const string DEFAULT_DOMAIN = 'pollora';

    public const string POST_TYPE = 'post_type';

    public const string TAXONOMY = 'taxonomy';

    /** The entry of the `labels` argument that asks for the default labels. */
    public const string DEFAULT_LABELS = '_pollora_default_labels';

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
     * The text domain of a Translatable that names none, and of the default labels,
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
            [$args, $names] = $this->withDefaultLabels($args, $names, $domain);
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
    private function withDefaultLabels(array $args, array $names, string $domain): array
    {
        $labels = $args['labels'];
        [$kind, $rawSingular, $rawPlural] = $labels[self::DEFAULT_LABELS];
        unset($labels[self::DEFAULT_LABELS]);

        $translating = ! $this->hasNothingToLookUp($domain);
        $singular = $translating ? $this->translateName($rawSingular, $domain) : $rawSingular;
        $plural = $translating ? $this->translateName($rawPlural, $domain) : $rawPlural;

        $args['labels'] = array_merge(
            $kind === self::TAXONOMY
                ? $this->taxonomyLabels($singular, $plural, $domain)
                : $this->postTypeLabels($singular, $plural, $domain),
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
     * A name written in the source language, translated in the text domain of the entity.
     */
    private function translateName(string $name, string $domain): string
    {
        return $name === '' || ! function_exists('__') ? $name : __($name, $domain);
    }

    /**
     * The default labels of a post type, from its singular and plural names.
     *
     * The templates are literal __() calls so that `wp i18n make-pot` still finds them.
     *
     * @param  string  $singular  The singular name
     * @param  string  $plural  The plural name
     * @return array<string, string> The labels array
     */
    private function postTypeLabels(string $singular, string $plural, string $textDomain): array
    {
        return [
            'name' => $plural,
            'singular_name' => $singular,
            /* translators: %s: post type general name (plural) */
            'add_new' => __('Add New', $textDomain),
            /* translators: %s: post type singular name */
            'add_new_item' => sprintf(__('Add New %s', $textDomain), $singular),
            /* translators: %s: post type singular name */
            'edit_item' => sprintf(__('Edit %s', $textDomain), $singular),
            /* translators: %s: post type singular name */
            'new_item' => sprintf(__('New %s', $textDomain), $singular),
            /* translators: %s: post type singular name */
            'view_item' => sprintf(__('View %s', $textDomain), $singular),
            /* translators: %s: post type general name (plural) */
            'view_items' => sprintf(__('View %s', $textDomain), $plural),
            /* translators: %s: post type general name (plural) */
            'search_items' => sprintf(__('Search %s', $textDomain), $plural),
            /* translators: %s: post type general name (plural) */
            'not_found' => sprintf(__('No %s found', $textDomain), $plural),
            /* translators: %s: post type general name (plural) */
            'not_found_in_trash' => sprintf(__('No %s found in Trash', $textDomain), $plural),
            /* translators: %s: post type singular name */
            'parent_item_colon' => sprintf(__('Parent %s:', $textDomain), $singular),
            /* translators: %s: post type general name (plural) */
            'all_items' => sprintf(__('All %s', $textDomain), $plural),
            /* translators: %s: post type singular name */
            'archives' => sprintf(__('%s Archives', $textDomain), $singular),
            /* translators: %s: post type singular name */
            'attributes' => sprintf(__('%s Attributes', $textDomain), $singular),
            /* translators: %s: post type singular name */
            'insert_into_item' => sprintf(__('Insert into %s', $textDomain), $singular),
            /* translators: %s: post type singular name */
            'uploaded_to_this_item' => sprintf(__('Uploaded to this %s', $textDomain), $singular),
        ];
    }

    /**
     * The default labels of a taxonomy, from its singular and plural names.
     *
     * The templates are literal __() calls so that `wp i18n make-pot` still finds them.
     *
     * @param  string  $singular  The singular name
     * @param  string  $plural  The plural name
     * @return array<string, string> The labels array
     */
    private function taxonomyLabels(string $singular, string $plural, string $textDomain): array
    {
        return [
            'name' => $plural,
            'singular_name' => $singular,
            'menu_name' => $plural,
            /* translators: %s: taxonomy general name (plural) */
            'all_items' => sprintf(__('All %s', $textDomain), $plural),
            /* translators: %s: taxonomy singular name */
            'edit_item' => sprintf(__('Edit %s', $textDomain), $singular),
            /* translators: %s: taxonomy singular name */
            'view_item' => sprintf(__('View %s', $textDomain), $singular),
            /* translators: %s: taxonomy singular name */
            'update_item' => sprintf(__('Update %s', $textDomain), $singular),
            /* translators: %s: taxonomy singular name */
            'add_new_item' => sprintf(__('Add New %s', $textDomain), $singular),
            /* translators: %s: taxonomy singular name */
            'new_item_name' => sprintf(__('New %s Name', $textDomain), $singular),
            /* translators: %s: taxonomy general name (plural) */
            'search_items' => sprintf(__('Search %s', $textDomain), $plural),
            /* translators: %s: taxonomy general name (plural) */
            'popular_items' => sprintf(__('Popular %s', $textDomain), $plural),
            /* translators: %s: taxonomy general name (plural) */
            'separate_items_with_commas' => sprintf(__('Separate %s with commas', $textDomain), $plural),
            /* translators: %s: taxonomy general name (plural) */
            'add_or_remove_items' => sprintf(__('Add or remove %s', $textDomain), $plural),
            /* translators: %s: taxonomy general name (plural) */
            'choose_from_most_used' => sprintf(__('Choose from the most used %s', $textDomain), $plural),
            /* translators: %s: taxonomy general name (plural) */
            'not_found' => sprintf(__('No %s found', $textDomain), $plural),
            /* translators: %s: taxonomy singular name */
            'parent_item' => sprintf(__('Parent %s', $textDomain), $singular),
            /* translators: %s: taxonomy singular name */
            'parent_item_colon' => sprintf(__('Parent %s:', $textDomain), $singular),
        ];
    }
}
