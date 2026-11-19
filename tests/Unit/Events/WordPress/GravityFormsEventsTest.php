<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntriesExported;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntryDeleted;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntryNoteAdded;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntryNoteDeleted;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntryReadUpdated;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntryStarredUpdated;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormActivated;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormDeactivated;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormDuplicated;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormsImported;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormViewsDeleted;
use Pollora\Events\WordPress\Plugins\GravityForms\GravityFormsEventDispatcher;
use Pollora\Events\WordPress\WordPressEventServiceProvider;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\HookInterface;
use Tests\Unit\Events\Fixtures\FakeError;

use function Tests\Unit\Events\Fixtures\make_dispatcher;

require_once __DIR__.'/fixtures.php';

if (! class_exists('GFAPI', false)) {
    eval('class GFAPI {
        public static array $forms = [];
        public static array $entries = [];
        public static function get_form($id) { return self::$forms[$id] ?? false; }
        public static function get_entry($id) { return self::$entries[$id] ?? false; }
    }');
}

/**
 * The hooks Gravity Forms 2.9.31 fires, with the number of arguments it passes
 * (the fewest, when the hook is fired from several places) and where.
 *
 * @return array<string, array{0: int, 1: string}>
 */
function gravity_forms_hook_arguments(): array
{
    return [
        'gform_after_save_form' => [3, 'includes/save-form/class-gf-form-crud-handler.php:184'],
        'gform_pre_notification_deleted' => [2, 'notification.php:1146'],
        'gform_pre_confirmation_deleted' => [2, 'forms_model.php:7448'],
        'gform_post_export_entries' => [5, 'export.php:1234'],
        'gform_forms_post_import' => [1, 'export.php:163'],
        'gform_delete_entry' => [1, 'forms_model.php:1850 and 2800'],
        'gform_post_note_added' => [6, 'includes/legacy/forms_model_legacy.php:2572 (7 in forms_model.php:2881)'],
        'gform_pre_note_deleted' => [2, 'forms_model.php:2951'],
        'gform_update_status' => [3, 'forms_model.php:1747 (gform_update_{$property_name})'],
        'gform_update_is_read' => [3, 'forms_model.php:1747 (gform_update_{$property_name})'],
        'gform_update_is_starred' => [3, 'forms_model.php:1747 (gform_update_{$property_name})'],
        'gform_before_delete_form' => [1, 'forms_model.php:1954'],
        'gform_post_form_trashed' => [1, 'forms_model.php:2014'],
        'gform_post_form_restored' => [1, 'forms_model.php:2043'],
        'gform_post_form_activated' => [1, 'forms_model.php:1528'],
        'gform_post_form_deactivated' => [1, 'forms_model.php:1538'],
        'gform_post_form_duplicated' => [2, 'forms_model.php:2111'],
        'gform_post_form_views_deleted' => [1, 'forms_model.php:1933'],
    ];
}

/**
 * Register a Gravity Forms dispatcher on a recording Action service.
 *
 * @return array<string, array{callback: array{0: object, 1: string}, args: int}>
 */
function gravity_forms_registered_hooks(): array
{
    $registered = [];

    $action = Mockery::mock(Action::class);
    $action->shouldReceive('add')->andReturnUsing(function (string $hook, array $callback, int $priority, int $args) use (&$registered) {
        $registered[$hook] = ['callback' => $callback, 'args' => $args];

        return Mockery::mock(HookInterface::class);
    });

    (new GravityFormsEventDispatcher(Mockery::mock(Dispatcher::class), $action))->registerEvents();

    return $registered;
}

describe('Gravity Forms hooks', function (): void {
    it('has a callable handler for every hook it registers', function (): void {
        $actions = (new ReflectionProperty(GravityFormsEventDispatcher::class, 'actions'))
            ->getValue((new ReflectionClass(GravityFormsEventDispatcher::class))->newInstanceWithoutConstructor());

        $missing = collect($actions)
            ->reject(fn (string $hook): bool => method_exists(GravityFormsEventDispatcher::class, 'handle'.Str::studly($hook))
                && (new ReflectionMethod(GravityFormsEventDispatcher::class, 'handle'.Str::studly($hook)))->isPublic())
            ->values()
            ->all();

        expect($actions)->not->toBeEmpty()
            ->and($missing)->toBe([]);
    });

    it('registers exactly the actions Gravity Forms fires', function (): void {
        expect(array_keys(gravity_forms_registered_hooks()))->toEqualCanonicalizing(array_keys(gravity_forms_hook_arguments()));
    });

    it('never hooks a filter, which would lose the value it has to return', function (): void {
        $hooks = array_keys(gravity_forms_registered_hooks());

        expect($hooks)->not->toContain('gform_pre_confirmation_save')
            ->and($hooks)->not->toContain('gform_pre_notification_save');
    });

    it('never hooks an action Gravity Forms does not fire', function (): void {
        $hooks = array_keys(gravity_forms_registered_hooks());

        expect($hooks)->not->toContain('gform_confirmation_status')
            ->and($hooks)->not->toContain('gform_notification_status');
    });

    it('does not hook the deprecated gform_delete_lead, which makes Gravity Forms raise a notice', function (): void {
        expect(array_keys(gravity_forms_registered_hooks()))->not->toContain('gform_delete_lead');
    });

    it('never asks a handler for more arguments than Gravity Forms passes', function (string $hook, int $passed): void {
        $registered = gravity_forms_registered_hooks()[$hook];
        $required = (new ReflectionMethod(...$registered['callback']))->getNumberOfRequiredParameters();

        expect($required)->toBeLessThanOrEqual(min($passed, $registered['args']));
    })->with(fn (): array => array_map(fn (string $hook, array $arguments): array => [$hook, $arguments[0]], array_keys(gravity_forms_hook_arguments()), gravity_forms_hook_arguments()));

    it('lets every handler receive all the arguments it declares', function (): void {
        $short = [];
        foreach (gravity_forms_registered_hooks() as $hook => $registered) {
            $declared = (new ReflectionMethod(...$registered['callback']))->getNumberOfParameters();
            if ($registered['args'] < min($declared, gravity_forms_hook_arguments()[$hook][0])) {
                $short[] = $hook;
            }
        }

        expect($short)->toBe([]);
    });
});

describe('Gravity Forms handlers', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(GravityFormsEventDispatcher::class);
        $this->form = ['id' => 3, 'title' => 'Contact'];
        $this->copy = ['id' => 4, 'title' => 'Contact (copy)'];
        $this->entry = ['id' => 11, 'form_id' => 3];

        GFAPI::$forms = [3 => $this->form, 4 => $this->copy];
        GFAPI::$entries = [11 => $this->entry, 12 => new FakeError('not_found', 'Entry not found')];
    });

    afterEach(function (): void {
        GFAPI::$forms = [];
        GFAPI::$entries = [];
    });

    it('dispatches the activation and the deactivation of a form', function (string $handler, string $expected): void {
        $this->dispatcher->{$handler}(3);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf($expected)
            ->and($this->log[0]->form)->toBe($this->form);
    })->with([
        'activated' => ['handleGformPostFormActivated', FormActivated::class],
        'deactivated' => ['handleGformPostFormDeactivated', FormDeactivated::class],
        'views deleted' => ['handleGformPostFormViewsDeleted', FormViewsDeleted::class],
    ]);

    it('ignores the activation, deactivation and views of a form that cannot be loaded', function (string $handler): void {
        $this->dispatcher->{$handler}(404);

        expect($this->log)->toHaveCount(0);
    })->with(['handleGformPostFormActivated', 'handleGformPostFormDeactivated', 'handleGformPostFormViewsDeleted']);

    it('dispatches FormDuplicated with the original and the duplicated form', function (): void {
        $this->dispatcher->handleGformPostFormDuplicated(3, 4);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(FormDuplicated::class)
            ->and($this->log[0]->form)->toBe($this->form)
            ->and($this->log[0]->duplicate)->toBe($this->copy);
    });

    it('ignores the duplication of a form when either form cannot be loaded', function (): void {
        $this->dispatcher->handleGformPostFormDuplicated(3, 404);
        $this->dispatcher->handleGformPostFormDuplicated(404, 4);

        expect($this->log)->toHaveCount(0);
    });

    it('dispatches FormsImported with the imported forms', function (): void {
        $this->dispatcher->handleGformFormsPostImport([$this->form, $this->copy]);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(FormsImported::class)
            ->and($this->log[0]->forms)->toBe([$this->form, $this->copy]);
    });

    it('dispatches EntriesExported with the details of the export', function (): void {
        $this->dispatcher->handleGformPostExportEntries($this->form, '2026-01-01', '2026-02-01', ['1', '2'], 'abc123');

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(EntriesExported::class)
            ->and($this->log[0]->form)->toBe($this->form)
            ->and($this->log[0]->startDate)->toBe('2026-01-01')
            ->and($this->log[0]->endDate)->toBe('2026-02-01')
            ->and($this->log[0]->fields)->toBe(['1', '2'])
            ->and($this->log[0]->exportId)->toBe('abc123');
    });

    it('dispatches EntriesExported for an export without dates', function (): void {
        $this->dispatcher->handleGformPostExportEntries($this->form, '', '', 'all', 'abc123');

        expect($this->log[0]->startDate)->toBe('')
            ->and($this->log[0]->endDate)->toBe('')
            ->and($this->log[0]->fields)->toBe('all');
    });

    it('dispatches EntryDeleted from gform_delete_entry', function (): void {
        $this->dispatcher->handleGformDeleteEntry(11);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(EntryDeleted::class)
            ->and($this->log[0]->entry)->toBe($this->entry);
    });

    it('ignores the deletion of an entry that Gravity Forms reports as a WP_Error', function (): void {
        $this->dispatcher->handleGformDeleteEntry(12);
        $this->dispatcher->handleGformDeleteLead(12);

        expect($this->log)->toHaveCount(0);
    });

    it('dispatches EntryNoteAdded from the six arguments Gravity Forms passes it', function (): void {
        $this->dispatcher->handleGformPostNoteAdded(5, 11, 2, 'Jane', 'Called back', 'user');

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(EntryNoteAdded::class)
            ->and($this->log[0]->noteType)->toBe('user');
    });

    it('dispatches EntryNoteAdded for a note added without a user', function (): void {
        $this->dispatcher->handleGformPostNoteAdded(5, '11', null, null, 'Imported', 'note');

        expect($this->log[0]->entry)->toBe($this->entry)
            ->and($this->log[0]->userId)->toBe(0)
            ->and($this->log[0]->userName)->toBe('');
    });

    it('dispatches EntryNoteDeleted with the note and its entry', function (): void {
        $this->dispatcher->handleGformPreNoteDeleted(5, 11);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(EntryNoteDeleted::class)
            ->and($this->log[0]->noteId)->toBe(5)
            ->and($this->log[0]->entry)->toBe($this->entry);
    });

    it('ignores the deletion of a note whose entry is unknown', function (): void {
        $this->dispatcher->handleGformPreNoteDeleted(5, null);
        $this->dispatcher->handleGformPreNoteDeleted(5, 404);
        $this->dispatcher->handleGformPreNoteDeleted(5, 12);

        expect($this->log)->toHaveCount(0);
    });

    it('dispatches the read and starred changes of an entry', function (string $handler, string $expected, string $property, string $previousProperty): void {
        $this->dispatcher->{$handler}(11, 1, '0');
        $this->dispatcher->{$handler}(11, '0', 1);

        expect($this->log)->toHaveCount(2)
            ->and($this->log[0])->toBeInstanceOf($expected)
            ->and($this->log[0]->entry)->toBe($this->entry)
            ->and($this->log[0]->{$property})->toBeTrue()
            ->and($this->log[0]->{$previousProperty})->toBeFalse()
            ->and($this->log[1]->{$property})->toBeFalse()
            ->and($this->log[1]->{$previousProperty})->toBeTrue();
    })->with([
        'read' => ['handleGformUpdateIsRead', EntryReadUpdated::class, 'isRead', 'wasRead'],
        'starred' => ['handleGformUpdateIsStarred', EntryStarredUpdated::class, 'isStarred', 'wasStarred'],
    ]);

    it('ignores the read and starred changes of an entry that cannot be loaded', function (string $handler): void {
        $this->dispatcher->{$handler}(404, 1, 0);
        $this->dispatcher->{$handler}(12, 1, 0);

        expect($this->log)->toHaveCount(0);
    })->with(['handleGformUpdateIsRead', 'handleGformUpdateIsStarred']);

    it('keeps the confirmation and notification deletions Gravity Forms reports for a missing item out of the events', function (): void {
        $this->dispatcher->handleGformPreNotificationDeleted(null, $this->form);
        $this->dispatcher->handleGformPreConfirmationDeleted(null, $this->form);

        expect($this->log)->toHaveCount(0);
    });
});

describe('Gravity Forms registration', function (): void {
    beforeEach(function (): void {
        $this->hooks = new ArrayObject;

        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->andReturnUsing(function (string $hook, callable|array $callback, int $priority = 10, int $args = 1) {
            $this->hooks[] = ['hook' => $hook, 'callback' => $callback, 'args' => $args];

            return Mockery::mock(HookInterface::class);
        });

        $this->app = new Application(sys_get_temp_dir());
        $this->app->instance(Dispatcher::class, Mockery::mock(Dispatcher::class));
        $this->app->instance(Action::class, $action);

        $this->provider = fn (bool $gravityForms, bool $pluginsLoaded): WordPressEventServiceProvider => new class($this->app, $gravityForms, $pluginsLoaded) extends WordPressEventServiceProvider
        {
            public function __construct($app, private readonly bool $gravityForms, private readonly bool $pluginsLoaded)
            {
                parent::__construct($app);
            }

            protected function gravityFormsIsLoaded(): bool
            {
                return $this->gravityForms;
            }

            protected function pluginsAreLoaded(): bool
            {
                return $this->pluginsLoaded;
            }
        };

        $this->gravityFormsHooks = fn (): array => collect($this->hooks)
            ->filter(fn (array $hook): bool => str_starts_with($hook['hook'], 'gform_') && $hook['hook'] !== 'gform_loaded')
            ->pluck('hook')
            ->all();
    });

    it('registers the Gravity Forms hooks when Gravity Forms is loaded', function (): void {
        $provider = ($this->provider)(true, true);
        $provider->register();
        $provider->boot();

        $hooks = ($this->gravityFormsHooks)();

        expect($hooks)->toContain('gform_post_form_duplicated')
            ->and($hooks)->toHaveCount(count(gravity_forms_hook_arguments()))
            ->and(collect($this->hooks)->pluck('hook'))->not->toContain('gform_loaded');
    });

    it('hooks nothing of Gravity Forms on a site without it', function (): void {
        $provider = ($this->provider)(false, true);
        $provider->register();
        $provider->boot();

        expect(($this->gravityFormsHooks)())->toBe([])
            ->and(collect($this->hooks)->pluck('hook'))->not->toContain('gform_loaded');
    });

    it('waits for Gravity Forms to load when the plugins are not loaded yet', function (): void {
        $provider = ($this->provider)(false, false);
        $provider->register();
        $provider->boot();

        expect(($this->gravityFormsHooks)())->toBe([]);

        $loaded = collect($this->hooks)->first(fn (array $hook): bool => $hook['hook'] === 'gform_loaded');
        expect($loaded)->not->toBeNull()
            ->and($loaded['args'])->toBe(0);

        $loaded['callback']();

        expect(($this->gravityFormsHooks)())->toHaveCount(count(gravity_forms_hook_arguments()));
    });

    it('shares one Gravity Forms dispatcher', function (): void {
        ($this->provider)(true, true)->register();

        expect($this->app->make(GravityFormsEventDispatcher::class))->toBe($this->app->make(GravityFormsEventDispatcher::class));
    });
});
