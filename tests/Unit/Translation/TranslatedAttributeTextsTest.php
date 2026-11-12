<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Pollora\Attributes\Ability;
use Pollora\Attributes\BlockBinding;
use Pollora\Attributes\BlockBinding\BindingField;
use Pollora\Attributes\CapabilitySet;
use Pollora\Attributes\Contracts\ConfiguresArguments;
use Pollora\Attributes\Contracts\ConfiguresArgumentsWithMethod;
use Pollora\Attributes\Meta;
use Pollora\Attributes\PostType;
use Pollora\Attributes\Role;
use Pollora\Attributes\Taxonomy;
use Pollora\Entity\Port\Out\EntityRegistryPort;
use Pollora\Translation\Infrastructure\Adapters\TranslatingEntityRegistry;

/*
 * The attributes declare which of their fields hold text shown to a person (pollora/attributes,
 * resources/i18n.php, read by the extraction). This test keeps the declaration and the registration
 * in step: every declared field of a post type or taxonomy attribute is translated when the entity
 * is registered, and nothing the declaration leaves out is translated.
 *
 * The declaration is read from the installed package, else from the main branch of a sibling
 * checkout of pollora/attributes (POLLORA_ATTRIBUTES_PATH, or a folder named `attributes` next to
 * the framework); the test is skipped when neither is there.
 */

/** The attributes whose fields end up in the registration arguments of a post type or a taxonomy. */
const I18N_DRIFT_REGISTRATION = [
    PostType::class,
    PostType\Labels::class,
    PostType\Label::class,
    PostType\Description::class,
    PostType\TitlePlaceholder::class,
    PostType\FeaturedImage::class,
    PostType\AdminCol::class,
    Taxonomy::class,
    Taxonomy\Labels::class,
    Taxonomy\Label::class,
    Taxonomy\Description::class,
];

/** The declared attributes that feed something else than the registration of a post type or a taxonomy. */
const I18N_DRIFT_ELSEWHERE = [
    Ability::class,
    BlockBinding::class,
    BindingField::class,
    CapabilitySet::class,
    Meta::class,
    Role::class,
];

/**
 * @return array<class-string, list<string>>|null
 */
function i18nDriftDeclaration(): ?array
{
    $installed = dirname((new ReflectionClass(PostType::class))->getFileName(), 2).'/resources/i18n.php';

    if (is_file($installed)) {
        return require $installed;
    }

    $framework = dirname(__DIR__, 3);
    $candidates = array_filter([getenv('POLLORA_ATTRIBUTES_PATH') ?: null, $framework.'/../attributes', $framework.'/../../../attributes']);

    foreach ($candidates as $folder) {
        if (! is_dir($folder.'/.git') && ! is_file($folder.'/.git')) {
            continue;
        }

        $source = shell_exec('git -C '.escapeshellarg($folder).' show origin/main:resources/i18n.php 2>/dev/null');

        if (is_string($source) && str_starts_with($source, '<?php')) {
            $file = tempnam(sys_get_temp_dir(), 'i18n-declaration');
            file_put_contents($file, $source);
            $declaration = require $file;
            unlink($file);

            return $declaration;
        }
    }

    return null;
}

/**
 * An attribute with a sentinel in one field and neutral values in the other required ones.
 *
 * @param  class-string  $class
 */
function i18nDriftAttribute(string $class, string $field, string $sentinel): object
{
    $arguments = [];

    foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
        if ($parameter->getName() === $field) {
            $arguments[$field] = $sentinel;
        } elseif (! $parameter->isOptional()) {
            $arguments[$parameter->getName()] = 'neutral';
        }
    }

    return new $class(...$arguments);
}

/**
 * The registration arguments an attribute writes, on top of the default labels the discovery asks for.
 *
 * @return array<string, mixed>
 */
function i18nDriftArguments(object $attribute, string $kind): array
{
    $arguments = ['labels' => TranslatingEntityRegistry::defaultLabels($kind, 'Name', 'Names'), 'text_domain' => 'drift-domain'];

    if ($attribute instanceof ConfiguresArgumentsWithMethod) {
        return $attribute->configureArguments($arguments, fn (): null => null);
    }

    if ($attribute instanceof ConfiguresArguments) {
        return $attribute->configureArguments($arguments);
    }

    return $arguments;
}

/**
 * Where a string sits in nested arguments, as a list of keys; null when it is nowhere.
 *
 * @param  array<array-key, mixed>  $arguments
 * @return list<array-key>|null
 */
function i18nDriftPath(array $arguments, string $needle): ?array
{
    foreach ($arguments as $key => $value) {
        if ($value === $needle) {
            return [$key];
        }

        if (is_array($value) && ($inner = i18nDriftPath($value, $needle)) !== null) {
            return [$key, ...$inner];
        }
    }

    return null;
}

/**
 * @param  array<array-key, mixed>  $arguments
 * @param  list<array-key>  $path
 */
function i18nDriftValue(array $arguments, array $path): mixed
{
    foreach ($path as $key) {
        $arguments = $arguments[$key];
    }

    return $arguments;
}

/**
 * Registers the arguments with a catalogue that turns every `sentinel` into `T:sentinel` in the domain of the entity.
 *
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed> What WordPress was given
 */
function i18nDriftWordPress(): void
{
    defined('WP_LANG_DIR') || define('WP_LANG_DIR', '/tmp/wp-lang');
    Functions\when('get_locale')->justReturn('fr_FR');
    $GLOBALS['l10n']['pollora'] = new stdClass;
    Functions\when('get_translations_for_domain')->justReturn(new stdClass);
    Functions\when('has_filter')->justReturn(false);
    Functions\when('translate')->alias(fn (string $text, string $domain = 'default'): string => $domain === 'drift-domain' && in_array($text, $GLOBALS['i18nDrift'], true) ? 'T:'.$text : $text);
    Functions\when('_x')->alias(fn (string $text): string => $text);

    // Without WordPress loaded, __() is Laravel's: the text domain arrives where the replacements would.
    Container::getInstance()->instance('translator', new class
    {
        public function get(string $key, mixed $replace = [], ?string $locale = null): string
        {
            $domain = is_string($replace) ? $replace : 'default';

            return $domain === 'drift-domain' && in_array($key, $GLOBALS['i18nDrift'], true) ? 'T:'.$key : $key;
        }
    });
}

function i18nDriftRegister(array $arguments, string $sentinel): array
{
    i18nDriftWordPress();
    $GLOBALS['i18nDrift'] = [$sentinel];

    $recorder = new class implements EntityRegistryPort
    {
        /** @var array<string, mixed> */
        public array $args = [];

        public function register(string $slug, array $args, array $names): void
        {
            $this->args = $args;
        }
    };

    (new TranslatingEntityRegistry($recorder))->register('drift', $arguments, ['singular' => 'Name', 'plural' => 'Names']);

    return $recorder->args;
}

/**
 * The fields of an attribute whose value is text, with the kind of registration that takes the attribute.
 *
 * @param  class-string  $class
 * @return list<string>
 */
function i18nDriftStringFields(string $class): array
{
    $fields = [];

    foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
        $type = (string) $parameter->getType();

        if (in_array(ltrim($type, '?'), ['string', 'bool|string', 'string|bool'], true)) {
            $fields[] = $parameter->getName();
        }
    }

    return $fields;
}

it('declares only attributes this test knows to be, or not to be, registration arguments', function (): void {
    $declaration = i18nDriftDeclaration() ?? $this->markTestSkipped('pollora/attributes does not ship resources/i18n.php here and no checkout of it was found.');

    $unknown = array_diff(array_keys($declaration), I18N_DRIFT_REGISTRATION, I18N_DRIFT_ELSEWHERE);

    expect($unknown)->toBe([], 'A new attribute declares translatable fields: say here whether the registration of a post type or a taxonomy takes them.')
        ->and(array_diff(I18N_DRIFT_REGISTRATION, array_keys($declaration)))->toBe([]);
});

it('translates on registration every declared field of the names of a post type and of a taxonomy, and only those', function (string $class, string $kind): void {
    $declaration = i18nDriftDeclaration() ?? $this->markTestSkipped('pollora/attributes does not ship resources/i18n.php here and no checkout of it was found.');

    expect($declaration[$class])->toBe(['singular', 'plural']);

    $recorder = new class implements EntityRegistryPort
    {
        /** @var array{args: array<string, mixed>, names: array<string, mixed>} */
        public array $registered = ['args' => [], 'names' => []];

        public function register(string $slug, array $args, array $names): void
        {
            $this->registered = ['args' => $args, 'names' => $names];
        }
    };
    i18nDriftWordPress();
    $GLOBALS['i18nDrift'] = ['S', 'P'];

    (new TranslatingEntityRegistry($recorder))->register('drift', ['labels' => TranslatingEntityRegistry::defaultLabels($kind, 'S', 'P'), 'text_domain' => 'drift-domain'], ['singular' => 'S', 'plural' => 'P']);

    expect($recorder->registered['names'])->toBe(['singular' => 'T:S', 'plural' => 'T:P'])
        ->and($recorder->registered['args']['labels']['singular_name'])->toBe('T:S')
        ->and($recorder->registered['args']['labels']['name'])->toBe('T:P');
})->with([
    'post type' => [PostType::class, 'post_type'],
    'taxonomy' => [Taxonomy::class, 'taxonomy'],
]);

it('translates on registration every declared field of an attribute that writes registration arguments', function (string $class, string $kind): void {
    $declaration = i18nDriftDeclaration() ?? $this->markTestSkipped('pollora/attributes does not ship resources/i18n.php here and no checkout of it was found.');

    expect($declaration[$class])->not->toBeEmpty();

    foreach ($declaration[$class] as $field) {
        $sentinel = 'sentinel-'.$field;
        $arguments = i18nDriftArguments(i18nDriftAttribute($class, $field, $sentinel), $kind);
        $path = i18nDriftPath($arguments, $sentinel);

        expect($path)->not->toBeNull("{$class}::\${$field} is declared translatable and reaches no registration argument.");

        $registered = i18nDriftRegister($arguments, $sentinel);

        expect(i18nDriftValue($registered, $path))->toBe('T:'.$sentinel, "{$class}::\${$field} is declared translatable and is not translated on registration (argument ".implode('.', $path).').');
    }
})->with([
    'post type labels' => [PostType\Labels::class, 'post_type'],
    'post type label' => [PostType\Label::class, 'post_type'],
    'post type description' => [PostType\Description::class, 'post_type'],
    'post type title placeholder' => [PostType\TitlePlaceholder::class, 'post_type'],
    'post type featured image' => [PostType\FeaturedImage::class, 'post_type'],
    'post type admin column' => [PostType\AdminCol::class, 'post_type'],
    'taxonomy labels' => [Taxonomy\Labels::class, 'taxonomy'],
    'taxonomy label' => [Taxonomy\Label::class, 'taxonomy'],
    'taxonomy description' => [Taxonomy\Description::class, 'taxonomy'],
]);

it('translates nothing an attribute leaves out of its declaration', function (string $class, string $kind): void {
    $declaration = i18nDriftDeclaration() ?? $this->markTestSkipped('pollora/attributes does not ship resources/i18n.php here and no checkout of it was found.');

    $translated = [];

    foreach (array_diff(i18nDriftStringFields($class), $declaration[$class]) as $field) {
        $sentinel = 'sentinel-'.$field;
        $arguments = i18nDriftArguments(i18nDriftAttribute($class, $field, $sentinel), $kind);
        $path = i18nDriftPath($arguments, $sentinel);

        if ($path === null) {
            continue;
        }

        $registered = i18nDriftRegister($arguments, $sentinel);

        if (i18nDriftValue($registered, $path) !== $sentinel) {
            $translated[] = "{$class}::\${$field} (argument ".implode('.', $path).')';
        }
    }

    expect($translated)->toBe([], 'Translated on registration, yet not declared translatable.');
})->with([
    'post type labels' => [PostType\Labels::class, 'post_type'],
    'post type label' => [PostType\Label::class, 'post_type'],
    'post type description' => [PostType\Description::class, 'post_type'],
    'post type title placeholder' => [PostType\TitlePlaceholder::class, 'post_type'],
    'post type featured image' => [PostType\FeaturedImage::class, 'post_type'],
    'post type admin column' => [PostType\AdminCol::class, 'post_type'],
    'taxonomy labels' => [Taxonomy\Labels::class, 'taxonomy'],
    'taxonomy label' => [Taxonomy\Label::class, 'taxonomy'],
    'taxonomy description' => [Taxonomy\Description::class, 'taxonomy'],
]);

it('lists as translated arguments exactly the ones a declared field of a single-text attribute writes', function (): void {
    $declaration = i18nDriftDeclaration() ?? $this->markTestSkipped('pollora/attributes does not ship resources/i18n.php here and no checkout of it was found.');
    $written = [];

    foreach ([PostType\Label::class, PostType\Description::class, PostType\TitlePlaceholder::class, PostType\FeaturedImage::class, Taxonomy\Label::class, Taxonomy\Description::class] as $class) {
        foreach ($declaration[$class] as $field) {
            $arguments = i18nDriftArguments(i18nDriftAttribute($class, $field, 'sentinel'), 'post_type');
            $written[] = i18nDriftPath($arguments, 'sentinel')[0];
        }
    }

    $written = array_values(array_unique($written));
    $translated = TranslatingEntityRegistry::TRANSLATED_ARGUMENTS;
    sort($written);
    sort($translated);

    expect($translated)->toBe($written);
});
