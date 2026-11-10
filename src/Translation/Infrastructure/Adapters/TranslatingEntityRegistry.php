<?php

declare(strict_types=1);

namespace Pollora\Translation\Infrastructure\Adapters;

use Pollora\Entity\Port\Out\EntityRegistryPort;
use Pollora\I18n\Runtime\Translator;

/**
 * Resolves the translatable texts of an entity just before WordPress registers it.
 *
 * The entity registration service calls its registry from the callback of the
 * `init` action, never earlier, so the text domains of themes and plugins are
 * loaded by then. This is the one place where the framework calls
 * Translator::resolve(): every post type and taxonomy goes through it, whether
 * it comes from a discovered class, the factory or a configuring() method.
 *
 * An argument without a Translatable or a TranslatablePlural comes back as it
 * was, so a project that uses none of them registers exactly what it did before.
 */
final readonly class TranslatingEntityRegistry implements EntityRegistryPort
{
    public const string DEFAULT_DOMAIN = 'pollora';

    public function __construct(private EntityRegistryPort $registry) {}

    /**
     * The text domain of a Translatable that names none is the `text_domain`
     * of the entity (the `textDomain` of its attribute), else `pollora`.
     *
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $names
     */
    public function register(string $slug, array $args, array $names): void
    {
        $textDomain = $args['text_domain'] ?? null;
        $domain = is_string($textDomain) && $textDomain !== '' ? $textDomain : self::DEFAULT_DOMAIN;

        $this->registry->register(
            $slug,
            Translator::resolve($args, $domain),
            Translator::resolve($names, $domain),
        );
    }
}
