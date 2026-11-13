<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Events\WordPress\Menu\MenuCreated;
use Pollora\Events\WordPress\Menu\MenuEventDispatcher;
use Pollora\Events\WordPress\Menu\MenuLocationChanged;
use Pollora\Events\WordPress\Menu\MenuUpdated;
use Pollora\Events\WordPress\Widget\WidgetAdded;
use Pollora\Events\WordPress\Widget\WidgetEventDispatcher;
use Pollora\Events\WordPress\Widget\WidgetMoved;
use Pollora\Events\WordPress\Widget\WidgetRemoved;
use Pollora\Events\WordPress\Widget\WidgetUpdated;
use Tests\Unit\Events\Fixtures\FakeTerm;

use function Tests\Unit\Events\Fixtures\make_dispatcher;

require_once __DIR__.'/fixtures.php';

describe('MenuEventDispatcher menus and locations', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(MenuEventDispatcher::class);
        $this->menus = [10 => new FakeTerm('primary'), 20 => new FakeTerm('footer')];

        Functions\when('get_term')->alias(fn (int $id, string $taxonomy): ?object => $taxonomy === 'nav_menu' ? ($this->menus[$id] ?? null) : null);
    });

    it('dispatches MenuCreated and MenuUpdated with the menu term', function (string $handler, string $expected): void {
        $this->dispatcher->{$handler}(10);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf($expected)
            ->and($this->log[0]->menu)->toBe($this->menus[10]);
    })->with([
        'wp_create_nav_menu' => ['handleWpCreateNavMenu', MenuCreated::class],
        'wp_update_nav_menu' => ['handleWpUpdateNavMenu', MenuUpdated::class],
    ]);

    it('ignores a menu that cannot be loaded', function (string $handler): void {
        $this->dispatcher->{$handler}(404);

        expect($this->log)->toHaveCount(0);
    })->with(['handleWpCreateNavMenu', 'handleWpUpdateNavMenu']);

    it('reports a menu assigned to a location', function (): void {
        $this->dispatcher->handleUpdateOption('theme_mods_nectar', [], ['nav_menu_locations' => ['primary' => 10]]);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(MenuLocationChanged::class)
            ->and($this->log[0]->menu)->toBe($this->menus[10])
            ->and($this->log[0]->getLocation())->toBe('primary')
            ->and($this->log[0]->isAssigned())->toBeTrue();
    });

    it('reports a menu removed from a location', function (): void {
        $this->dispatcher->handleUpdateOption('theme_mods_nectar', ['nav_menu_locations' => ['primary' => 10]], []);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0]->menu)->toBe($this->menus[10])
            ->and($this->log[0]->getLocation())->toBe('primary')
            ->and($this->log[0]->isAssigned())->toBeFalse();
    });

    it('reports a replacement as an unassignment of the old menu then an assignment of the new one', function (): void {
        $this->dispatcher->handleUpdateOption(
            'theme_mods_nectar',
            ['nav_menu_locations' => ['primary' => 10]],
            ['nav_menu_locations' => ['primary' => 20]],
        );

        expect($this->log)->toHaveCount(2)
            ->and($this->log[0]->menu)->toBe($this->menus[10])
            ->and($this->log[0]->isAssigned())->toBeFalse()
            ->and($this->log[1]->menu)->toBe($this->menus[20])
            ->and($this->log[1]->isAssigned())->toBeTrue()
            ->and($this->log[1]->getLocation())->toBe('primary');
    });

    it('reports nothing for a location whose menu did not change', function (): void {
        $this->dispatcher->handleUpdateOption(
            'theme_mods_nectar',
            ['nav_menu_locations' => ['primary' => 10, 'footer' => 20]],
            ['nav_menu_locations' => ['primary' => 10, 'footer' => 10]],
        );

        expect($this->log)->toHaveCount(2)
            ->and($this->log[0]->getLocation())->toBe('footer')
            ->and($this->log[0]->menu)->toBe($this->menus[20])
            ->and($this->log[1]->getLocation())->toBe('footer')
            ->and($this->log[1]->menu)->toBe($this->menus[10]);
    });

    it('ignores options that are not theme modifications', function (): void {
        $this->dispatcher->handleUpdateOption('blogname', [], ['nav_menu_locations' => ['primary' => 10]]);

        expect($this->log)->toHaveCount(0);
    });

    it('skips a location whose menu no longer exists', function (): void {
        $this->dispatcher->handleUpdateOption('theme_mods_nectar', ['nav_menu_locations' => ['primary' => 404]], ['nav_menu_locations' => ['primary' => 10]]);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0]->menu)->toBe($this->menus[10])
            ->and($this->log[0]->isAssigned())->toBeTrue();
    });
});

describe('WidgetEventDispatcher', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(WidgetEventDispatcher::class);

        $GLOBALS['wp_widget_factory'] = (object) ['widgets' => [
            (object) ['id_base' => 'search', 'name' => 'Search'],
            (object) ['id_base' => 'text', 'name' => 'Text'],
        ]];

        Functions\when('did_action')->justReturn(0);
        Functions\when('get_option')->alias(fn (string $name, mixed $default = false): mixed => match ($name) {
            'widget_text' => [2 => ['title' => 'Hello'], 3 => ['title' => 'World']],
            default => $default,
        });
        Functions\when('wp_get_sidebars_widgets')->justReturn([
            'sidebar-1' => ['search-1'],
            'footer' => ['text-2'],
            'inactive' => 'not-a-list',
        ]);
    });

    afterEach(function (): void {
        unset($GLOBALS['wp_widget_factory']);
    });

    it('reports a widget added to a sidebar with its name and title', function (): void {
        $this->dispatcher->handleUpdateOptionSidebarsWidgets(['footer' => []], ['footer' => ['text-2']]);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(WidgetAdded::class)
            ->and($this->log[0]->widgetId)->toBe('text-2')
            ->and($this->log[0]->widgetName)->toBe('Text')
            ->and($this->log[0]->widgetTitle)->toBe('Hello')
            ->and($this->log[0]->sidebarId)->toBe('footer');
    });

    it('reports a widget added to a sidebar that did not exist before', function (): void {
        $this->dispatcher->handleUpdateOptionSidebarsWidgets([], ['footer' => ['text-3']]);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(WidgetAdded::class)
            ->and($this->log[0]->widgetId)->toBe('text-3')
            ->and($this->log[0]->widgetTitle)->toBe('World');
    });

    it('reports a widget removed from a sidebar', function (): void {
        $this->dispatcher->handleUpdateOptionSidebarsWidgets(['footer' => ['text-2', 'text-3']], ['footer' => ['text-3']]);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(WidgetRemoved::class)
            ->and($this->log[0]->widgetId)->toBe('text-2')
            ->and($this->log[0]->widgetName)->toBe('Text')
            ->and($this->log[0]->widgetTitle)->toBe('Hello')
            ->and($this->log[0]->sidebarId)->toBe('footer');
    });

    it('reports a widget moved from one sidebar to another', function (): void {
        $this->dispatcher->handleUpdateOptionSidebarsWidgets(
            ['sidebar-1' => ['text-2'], 'footer' => []],
            ['sidebar-1' => [], 'footer' => ['text-2']],
        );

        $moved = collect($this->log)->first(fn (object $event): bool => $event instanceof WidgetMoved);

        expect($moved)->not->toBeNull()
            ->and($moved->widgetId)->toBe('text-2')
            ->and($moved->oldSidebarId)->toBe('sidebar-1')
            ->and($moved->newSidebarId)->toBe('footer')
            ->and($moved->widgetName)->toBe('Text')
            ->and($moved->widgetTitle)->toBe('Hello');
    });

    it('reports nothing when the sidebars did not change', function (): void {
        $this->dispatcher->handleUpdateOptionSidebarsWidgets(['footer' => ['text-2']], ['footer' => ['text-2']]);

        expect($this->log)->toHaveCount(0);
    });

    it('ignores sidebar changes that come from a theme switch', function (): void {
        Functions\when('did_action')->alias(fn (string $hook): int => $hook === 'after_switch_theme' ? 1 : 0);

        $this->dispatcher->handleUpdateOptionSidebarsWidgets([], ['footer' => ['text-2']]);

        expect($this->log)->toHaveCount(0);
    });

    it('skips sidebars that are not lists of widgets', function (): void {
        $this->dispatcher->handleUpdateOptionSidebarsWidgets([], ['array_version' => 3, 'footer' => ['text-2']]);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0]->widgetId)->toBe('text-2');
    });

    it('treats an unreadable previous sidebar as empty', function (): void {
        $this->dispatcher->handleUpdateOptionSidebarsWidgets(['footer' => 'broken'], ['footer' => ['text-2']]);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(WidgetAdded::class);
    });

    it('gives no name or title to a widget whose id has no instance number', function (): void {
        $this->dispatcher->handleUpdateOptionSidebarsWidgets([], ['footer' => ['custom']]);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0]->widgetId)->toBe('custom')
            ->and($this->log[0]->widgetName)->toBeNull()
            ->and($this->log[0]->widgetTitle)->toBeNull();
    });

    it('gives no name to a widget type WordPress does not know', function (): void {
        $this->dispatcher->handleUpdateOptionSidebarsWidgets([], ['footer' => ['gallery-4']]);

        expect($this->log[0]->widgetName)->toBeNull()
            ->and($this->log[0]->widgetTitle)->toBeNull();
    });

    it('reports an updated widget instance with both versions and its sidebar', function (): void {
        $old = [2 => ['title' => 'Hello'], 3 => ['title' => 'World']];
        $new = [2 => ['title' => 'Bonjour'], 3 => ['title' => 'World']];

        $this->dispatcher->handleUpdatedOption('widget_text', $old, $new);

        $updated = collect($this->log)->filter(fn (object $event): bool => $event instanceof WidgetUpdated)->values();

        expect($updated)->toHaveCount(2)
            ->and($updated[0]->widgetId)->toBe('text-2')
            ->and($updated[0]->oldInstance)->toBe(['title' => 'Hello'])
            ->and($updated[0]->newInstance)->toBe(['title' => 'Bonjour'])
            ->and($updated[0]->widgetName)->toBe('Text')
            ->and($updated[0]->widgetTitle)->toBe('Hello')
            ->and($updated[0]->sidebarId)->toBe('footer')
            ->and($updated[1]->widgetId)->toBe('text-3')
            ->and($updated[1]->sidebarId)->toBeNull();
    });

    it('does not report instances that did not exist before', function (): void {
        $this->dispatcher->handleUpdatedOption('widget_text', [2 => ['title' => 'Hello']], [2 => ['title' => 'Hello'], 9 => ['title' => 'New']]);

        expect(collect($this->log)->map(fn (object $event): string => $event->widgetId)->all())->toBe(['text-2']);
    });

    it('ignores options that are not widget options', function (): void {
        $this->dispatcher->handleUpdatedOption('blogname', [2 => ['a']], [2 => ['b']]);

        expect($this->log)->toHaveCount(0);
    });

    it('ignores widget options that are not arrays', function (mixed $old, mixed $new): void {
        $this->dispatcher->handleUpdatedOption('widget_text', $old, $new);

        expect($this->log)->toHaveCount(0);
    })->with([
        'old is false' => [false, [2 => ['title' => 'x']]],
        'new is a string' => [[2 => ['title' => 'x']], 'x'],
    ]);
});
