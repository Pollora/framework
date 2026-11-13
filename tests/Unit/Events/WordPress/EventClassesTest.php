<?php

declare(strict_types=1);

use Tests\Unit\Events\Fixtures\FakeComment;
use Tests\Unit\Events\Fixtures\FakePost;
use Tests\Unit\Events\Fixtures\FakeSite;
use Tests\Unit\Events\Fixtures\FakeTerm;
use Tests\Unit\Events\Fixtures\FakeUser;

require_once __DIR__.'/fixtures.php';

/**
 * Every concrete event class of src/Events/WordPress.
 *
 * @return array<string, array{0: class-string}>
 */
function pollora_concrete_event_classes(): array
{
    $root = dirname(__DIR__, 4).'/src/Events/WordPress';
    $classes = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $name = substr($file->getPathname(), strlen($root) + 1, -4);

        if (str_ends_with($name, 'Dispatcher') || str_ends_with($name, 'Provider')) {
            continue;
        }

        $class = 'Pollora\\Events\\WordPress\\'.str_replace('/', '\\', $name);

        if ((new ReflectionClass($class))->isInstantiable()) {
            $classes[$class] = [$class];
        }
    }

    ksort($classes);

    return $classes;
}

/**
 * A distinct, recognisable value for every constructor parameter.
 */
function pollora_event_argument(ReflectionParameter $parameter, int $index): mixed
{
    $type = $parameter->getType();
    $name = $parameter->getName();

    $typeName = $type instanceof ReflectionNamedType ? ltrim($type->getName(), '\\') : 'mixed';

    return match ($typeName) {
        'WP_Post' => new FakePost('post-'.$name),
        'WP_User' => new FakeUser(100 + $index),
        'WP_Comment' => new FakeComment('comment'),
        'WP_Site' => new FakeSite(200 + $index),
        'WP_Term' => new FakeTerm('term-'.$name),
        'string' => 'value-'.$name,
        'int' => 1000 + $index,
        'bool' => true,
        'array' => [$name => 'item-'.$index],
        default => 'mixed-'.$name,
    };
}

describe('WordPress event classes', function (): void {
    it('covers the whole event catalogue', function (): void {
        // Concrete events only: a new event class must be added to the catalogue knowingly.
        expect(pollora_concrete_event_classes())->toHaveCount(85);
    });

    it('exposes every constructor argument unchanged', function (string $class): void {
        $reflection = new ReflectionClass($class);
        $parameters = $reflection->getConstructor()->getParameters();

        $arguments = [];
        foreach ($parameters as $index => $parameter) {
            $arguments[$parameter->getName()] = pollora_event_argument($parameter, $index);
        }

        $event = new $class(...$arguments);

        foreach ($arguments as $name => $expected) {
            $getter = null;
            foreach (['get'.ucfirst($name), 'is'.ucfirst($name)] as $candidate) {
                if ($reflection->hasMethod($candidate)) {
                    $getter = $candidate;
                }
            }

            $actual = $getter !== null ? $event->{$getter}() : $event->{$name};

            expect($actual)->toBe($expected, sprintf('%s::$%s', $class, $name));
        }
    })->with(fn (): array => pollora_concrete_event_classes());

    it('does not mix up two arguments of the same type', function (): void {
        $first = new FakeTerm('first');
        $second = new FakeTerm('second');

        $event = new Pollora\Events\WordPress\Taxonomy\TermUpdated($first, $second);

        expect($event->term)->toBe($first)
            ->and($event->previousTerm)->toBe($second);
    });
});

describe('WordPress event classes with derived state', function (): void {
    it('keeps a moved widget in its new sidebar', function (): void {
        $event = new Pollora\Events\WordPress\Widget\WidgetMoved('text-2', 'sidebar-1', 'footer', 'Text', 'Hello');

        expect($event->widgetId)->toBe('text-2')
            ->and($event->oldSidebarId)->toBe('sidebar-1')
            ->and($event->newSidebarId)->toBe('footer')
            ->and($event->sidebarId)->toBe('footer')
            ->and($event->widgetName)->toBe('Text')
            ->and($event->widgetTitle)->toBe('Hello');
    });

    it('identifies a reordered sidebar rather than a widget', function (): void {
        $event = new Pollora\Events\WordPress\Widget\WidgetReordered('sidebar-1', ['a', 'b'], ['b', 'a']);

        expect($event->sidebarId)->toBe('sidebar-1')
            ->and($event->widgetId)->toBe('')
            ->and($event->widgetName)->toBeNull()
            ->and($event->oldOrder)->toBe(['a', 'b'])
            ->and($event->newOrder)->toBe(['b', 'a']);
    });

    it('defaults optional data of installer events', function (): void {
        $plugin = new Pollora\Events\WordPress\Installer\Plugin\PluginActivated('Akismet');
        $theme = new Pollora\Events\WordPress\Installer\Theme\ThemeActivated('Nectar');

        expect($plugin->name)->toBe('Akismet')
            ->and($plugin->version)->toBeNull()
            ->and($plugin->slug)->toBeNull()
            ->and($plugin->networkWide)->toBeFalse()
            ->and($theme->name)->toBe('Nectar')
            ->and($theme->version)->toBeNull();
    });

    it('defaults optional data of user and two-factor events', function (): void {
        $user = new FakeUser(5);

        $created = new Pollora\Events\WordPress\User\UserCreated($user);
        $changed = new Pollora\Events\WordPress\Plugins\TwoFactor\TwoFactorConfigurationChanged($user, 'enabled', 'totp');

        expect($created->creator)->toBeNull()
            ->and($changed->oldValue)->toBeNull()
            ->and($changed->newValue)->toBeNull();
    });
});
