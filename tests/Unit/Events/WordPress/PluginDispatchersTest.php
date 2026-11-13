<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Pollora\Events\WordPress\Plugins\GravityForms\Confirmation\ConfirmationCreated;
use Pollora\Events\WordPress\Plugins\GravityForms\Confirmation\ConfirmationDeleted;
use Pollora\Events\WordPress\Plugins\GravityForms\Confirmation\ConfirmationUpdated;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntryDeleted;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntryNoteAdded;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntryStatusUpdated;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormCreated;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormDeleted;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormRestored;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormTrashed;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormUpdated;
use Pollora\Events\WordPress\Plugins\GravityForms\GravityFormsEventDispatcher;
use Pollora\Events\WordPress\Plugins\GravityForms\Notification\NotificationCreated;
use Pollora\Events\WordPress\Plugins\GravityForms\Notification\NotificationDeleted;
use Pollora\Events\WordPress\Plugins\GravityForms\Notification\NotificationUpdated;
use Pollora\Events\WordPress\Plugins\TwoFactor\TwoFactorAuthenticated;
use Pollora\Events\WordPress\Plugins\TwoFactor\TwoFactorAuthenticationFailed;
use Pollora\Events\WordPress\Plugins\TwoFactor\TwoFactorConfigurationChanged;
use Pollora\Events\WordPress\Plugins\TwoFactor\TwoFactorEventDispatcher;
use Pollora\Events\WordPress\Plugins\YoastSeo\FileUpdated;
use Pollora\Events\WordPress\Plugins\YoastSeo\MetaAdded;
use Pollora\Events\WordPress\Plugins\YoastSeo\MetaDeleted;
use Pollora\Events\WordPress\Plugins\YoastSeo\MetaUpdated;
use Pollora\Events\WordPress\Plugins\YoastSeo\SettingsExported;
use Pollora\Events\WordPress\Plugins\YoastSeo\SettingsImported;
use Pollora\Events\WordPress\Plugins\YoastSeo\YoastSeoEventDispatcher;
use Tests\Unit\Events\Fixtures\FakeError;
use Tests\Unit\Events\Fixtures\FakeUser;

use function Tests\Unit\Events\Fixtures\make_dispatcher;

require_once __DIR__.'/fixtures.php';

/**
 * Makes Request::post() read the given POST payload.
 *
 * @param  array<string, mixed>  $post
 */
function pollora_post_request(array $post): void
{
    $app = new Container;
    $app->instance('request', Request::create('/wp-admin/admin.php', 'POST', $post));

    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
}

afterEach(function (): void {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

describe('YoastSeoEventDispatcher', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(YoastSeoEventDispatcher::class);
    });

    it('maps the post meta hooks of Yoast keys to their event', function (string $handler, string $expected): void {
        $this->dispatcher->{$handler}([5], 42, '_yoast_wpseo_title', 'My title');

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf($expected)
            ->and($this->log[0]->objectId)->toBe(42)
            ->and($this->log[0]->metaKey)->toBe('_yoast_wpseo_title')
            ->and($this->log[0]->metaValue)->toBe('My title');
    })->with([
        'added_post_meta' => ['handleAddedPostMeta', MetaAdded::class],
        'updated_post_meta' => ['handleUpdatedPostMeta', MetaUpdated::class],
        'deleted_post_meta' => ['handleDeletedPostMeta', MetaDeleted::class],
    ]);

    it('ignores post meta that does not belong to Yoast', function (string $handler, string $key): void {
        $this->dispatcher->{$handler}([5], 42, $key, 'value');

        expect($this->log)->toHaveCount(0);
    })->with([
        ['handleAddedPostMeta', '_thumbnail_id'],
        ['handleUpdatedPostMeta', 'my_yoast_wpseo_'],
        ['handleDeletedPostMeta', '_yoast_wpseo'],
    ]);

    it('dispatches SettingsImported for each import the form asked for', function (): void {
        pollora_post_request(['wpseo' => ['importheadspace' => '1', 'importwoo' => '1', 'deleteolddata' => '1']]);

        $this->dispatcher->handleWpseoHandleImport();

        expect($this->log)->toHaveCount(2)
            ->and($this->log[0])->toBeInstanceOf(SettingsImported::class)
            ->and($this->log[0]->source)->toBe('HeadSpace2')
            ->and($this->log[0]->deleteOldData)->toBeTrue()
            ->and($this->log[1]->source)->toBe('WooThemes SEO framework')
            ->and($this->log[1]->deleteOldData)->toBeTrue();
    });

    it('keeps the old data when the import did not ask to delete it', function (): void {
        pollora_post_request(['wpseo' => ['importaioseo' => '1']]);

        $this->dispatcher->handleWpseoHandleImport();

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0]->source)->toBe('All-in-One SEO')
            ->and($this->log[0]->deleteOldData)->toBeFalse();
    });

    it('names every importer by the tool it imports from', function (string $field, string $source): void {
        pollora_post_request(['wpseo' => [$field => '1']]);

        $this->dispatcher->handleWpseoHandleImport();

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0]->source)->toBe($source);
    })->with([
        ['importheadspace', 'HeadSpace2'],
        ['importaioseo', 'All-in-One SEO'],
        ['importaioseoold', 'OLD All-in-One SEO'],
        ['importwoo', 'WooThemes SEO framework'],
        ['importrobotsmeta', 'Robots Meta (by Yoast)'],
        ['importrssfooter', 'RSS Footer (by Yoast)'],
        ['importbreadcrumbs', 'Yoast Breadcrumbs'],
    ]);

    it('dispatches nothing when no import was requested', function (): void {
        pollora_post_request([]);

        $this->dispatcher->handleWpseoHandleImport();

        expect($this->log)->toHaveCount(0);
    });

    it('dispatches SettingsExported with the taxonomy meta choice', function (bool $included): void {
        pollora_post_request(['wpseo' => ['export' => '1'] + ($included ? ['include_taxonomy_meta' => '1'] : [])]);

        $this->dispatcher->handleWpseoImport();

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(SettingsExported::class)
            ->and($this->log[0]->includeTaxonomyMeta)->toBe($included);
    })->with([true, false]);

    it('dispatches nothing when the form did not ask for an export', function (): void {
        pollora_post_request(['wpseo' => ['include_taxonomy_meta' => '1']]);

        $this->dispatcher->handleWpseoImport();

        expect($this->log)->toHaveCount(0);
    });

    it('dispatches FileUpdated with the file action that was submitted', function (string $field, string $action): void {
        pollora_post_request([$field => '1']);

        $this->dispatcher->handleSeoPageWpseoFiles();

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(FileUpdated::class)
            ->and($this->log[0]->action)->toBe($action);
    })->with([
        ['create_robots', 'create_robots'],
        ['submitrobots', 'update_robots'],
        ['submithtaccess', 'update_htaccess'],
    ]);

    it('dispatches nothing when no file form was submitted', function (): void {
        pollora_post_request(['other' => '1']);

        $this->dispatcher->handleSeoPageWpseoFiles();

        expect($this->log)->toHaveCount(0);
    });
});

describe('TwoFactorEventDispatcher', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(TwoFactorEventDispatcher::class);
        $this->user = new FakeUser(7);

        Functions\when('get_user_by')->alias(fn (string $field, mixed $value): ?object => match ([$field, $value]) {
            ['ID', 7], ['login', 'jane'], ['email', 'jane@example.com'] => $this->user,
            default => null,
        });
        Functions\when('is_email')->alias(fn (string $value): bool => str_contains($value, '@'));
        Functions\when('get_user_meta')->alias(fn (int $id, string $key): mixed => $key === '_two_factor_enabled_providers' ? ['totp'] : 'stored-'.$key);
    });

    it('dispatches TwoFactorAuthenticated with the key of the provider that was used', function (): void {
        $provider = new class
        {
            public function get_key(): string
            {
                return 'Two_Factor_Totp';
            }
        };

        $this->dispatcher->handleTwoFactorUserAuthenticated($this->user, $provider);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(TwoFactorAuthenticated::class)
            ->and($this->log[0]->user)->toBe($this->user)
            ->and($this->log[0]->provider)->toBe('Two_Factor_Totp');
    });

    it('names the provider unknown when WordPress gives none', function (): void {
        $this->dispatcher->handleTwoFactorUserAuthenticated($this->user);

        expect($this->log[0]->provider)->toBe('unknown');
    });

    it('dispatches TwoFactorAuthenticationFailed with the error code and message', function (string $login): void {
        $this->dispatcher->handleWpLoginFailed($login, new FakeError('two_factor_invalid_code', 'Invalid code.'));

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(TwoFactorAuthenticationFailed::class)
            ->and($this->log[0]->user)->toBe($this->user)
            ->and($this->log[0]->errorCode)->toBe('two_factor_invalid_code')
            ->and($this->log[0]->errorMessage)->toBe('Invalid code.');
    })->with(['by login' => 'jane', 'by e-mail' => 'jane@example.com']);

    it('ignores login failures that are not two-factor failures', function (): void {
        $this->dispatcher->handleWpLoginFailed('jane', new FakeError('incorrect_password', 'Wrong.'));

        expect($this->log)->toHaveCount(0);
    });

    it('ignores a two-factor failure for an unknown user', function (string $login): void {
        $this->dispatcher->handleWpLoginFailed($login, new FakeError('two_factor_invalid_code', 'Invalid code.'));

        expect($this->log)->toHaveCount(0);
    })->with(['nobody', 'nobody@example.com']);

    it('reports an updated backup code list with the previous and the new value', function (): void {
        $this->dispatcher->handleUpdateUserMeta([1], 7, '_two_factor_backup_codes', 'new');
        $this->dispatcher->handleUpdatedUserMeta([1], 7, '_two_factor_backup_codes', 'new');

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(TwoFactorConfigurationChanged::class)
            ->and($this->log[0]->user)->toBe($this->user)
            ->and($this->log[0]->action)->toBe('updated')
            ->and($this->log[0]->provider)->toBe('backup_codes')
            ->and($this->log[0]->oldValue)->toBe(['stored-_two_factor_backup_codes'])
            ->and($this->log[0]->newValue)->toBe(['new']);
    });

    it('reports a newly added TOTP key without a previous value', function (): void {
        $this->dispatcher->handleAddedUserMeta([1], 7, '_two_factor_totp_key', 'secret');

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0]->action)->toBe('updated')
            ->and($this->log[0]->provider)->toBe('totp_key')
            ->and($this->log[0]->oldValue)->toBeNull()
            ->and($this->log[0]->newValue)->toBe(['secret']);
    });

    it('reports a cleared TOTP key with a null new value', function (): void {
        $this->dispatcher->handleUpdateUserMeta([1], 7, '_two_factor_totp_key', '');
        $this->dispatcher->handleUpdatedUserMeta([1], 7, '_two_factor_totp_key', '');

        expect($this->log[0]->oldValue)->toBe(['stored-_two_factor_totp_key'])
            ->and($this->log[0]->newValue)->toBeNull();
    });

    it('reports each provider that was enabled and each that was disabled', function (): void {
        // get_user_meta() returns the stored providers, ['totp'], when the update is announced.
        $this->dispatcher->handleUpdateUserMeta([1], 7, '_two_factor_enabled_providers', ['email', 'backup']);
        $this->dispatcher->handleUpdatedUserMeta([1], 7, '_two_factor_enabled_providers', ['email', 'backup']);

        expect($this->log)->toHaveCount(3)
            ->and($this->log[0]->action)->toBe('enabled')
            ->and($this->log[0]->provider)->toBe('email')
            ->and($this->log[0]->oldValue)->toBe(['totp'])
            ->and($this->log[0]->newValue)->toBe(['email', 'backup'])
            ->and($this->log[1]->action)->toBe('enabled')
            ->and($this->log[1]->provider)->toBe('backup')
            ->and($this->log[2]->action)->toBe('disabled')
            ->and($this->log[2]->provider)->toBe('totp')
            ->and($this->log[2]->oldValue)->toBe(['totp'])
            ->and($this->log[2]->newValue)->toBe(['email', 'backup']);
    });

    it('forgets the stored previous value once it has been used', function (): void {
        $this->dispatcher->handleUpdateUserMeta([1], 7, '_two_factor_totp_key', 'x');
        $this->dispatcher->handleUpdatedUserMeta([1], 7, '_two_factor_totp_key', 'y');
        $this->dispatcher->handleUpdatedUserMeta([1], 7, '_two_factor_totp_key', 'z');

        expect($this->log)->toHaveCount(2)
            ->and($this->log[0]->oldValue)->toBe(['stored-_two_factor_totp_key'])
            ->and($this->log[1]->oldValue)->toBeNull();
    });

    it('ignores metadata that is not two-factor configuration', function (): void {
        $this->dispatcher->handleUpdateUserMeta([1], 7, 'nickname', 'x');
        $this->dispatcher->handleUpdatedUserMeta([1], 7, 'nickname', 'x');
        $this->dispatcher->handleAddedUserMeta([1], 7, 'nickname', 'x');

        expect($this->log)->toHaveCount(0);
    });

    it('ignores configuration changes of an unknown user', function (): void {
        $this->dispatcher->handleUpdatedUserMeta([1], 404, '_two_factor_totp_key', 'x');
        $this->dispatcher->handleAddedUserMeta([1], 404, '_two_factor_totp_key', 'x');

        expect($this->log)->toHaveCount(0);
    });
});

if (! class_exists('GFAPI', false)) {
    eval('class GFAPI {
        public static array $forms = [];
        public static array $entries = [];
        public static function get_form($id) { return self::$forms[$id] ?? false; }
        public static function get_entry($id) { return self::$entries[$id] ?? false; }
    }');
}

describe('GravityFormsEventDispatcher', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(GravityFormsEventDispatcher::class);
        $this->form = ['id' => 3, 'title' => 'Contact'];
        $this->entry = ['id' => 11, 'form_id' => 3];

        GFAPI::$forms = [3 => $this->form];
        GFAPI::$entries = [11 => $this->entry];
    });

    afterEach(function (): void {
        GFAPI::$forms = [];
        GFAPI::$entries = [];
    });

    it('tells a created form from an updated one', function (bool $isNew, string $expected): void {
        $this->dispatcher->handleGformAfterSaveForm($this->form, $isNew);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf($expected)
            ->and($this->log[0]->form)->toBe($this->form);
    })->with([
        'new' => [true, FormCreated::class],
        'existing' => [false, FormUpdated::class],
    ]);

    it('tells a created confirmation from an updated one', function (bool $isNew, string $expected): void {
        $this->dispatcher->handleGformPreConfirmationSave(['id' => 'c1'], $this->form, $isNew);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf($expected)
            ->and($this->log[0]->confirmation)->toBe(['id' => 'c1'])
            ->and($this->log[0]->form)->toBe($this->form);
    })->with([
        'new' => [true, ConfirmationCreated::class],
        'existing' => [false, ConfirmationUpdated::class],
    ]);

    it('tells a created notification from an updated one', function (bool $isNew, string $expected): void {
        $this->dispatcher->handleGformPreNotificationSave(['id' => 'n1'], $this->form, $isNew);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf($expected)
            ->and($this->log[0]->notification)->toBe(['id' => 'n1'])
            ->and($this->log[0]->form)->toBe($this->form);
    })->with([
        'new' => [true, NotificationCreated::class],
        'existing' => [false, NotificationUpdated::class],
    ]);

    it('treats a confirmation or notification saved without a flag as new', function (): void {
        $this->dispatcher->handleGformPreConfirmationSave(['id' => 'c1'], $this->form);
        $this->dispatcher->handleGformPreNotificationSave(['id' => 'n1'], $this->form);

        expect($this->log[0])->toBeInstanceOf(ConfirmationCreated::class)
            ->and($this->log[1])->toBeInstanceOf(NotificationCreated::class);
    });

    it('dispatches the deletion of a notification and of a confirmation', function (): void {
        $this->dispatcher->handleGformPreNotificationDeleted(['id' => 'n1'], $this->form);
        $this->dispatcher->handleGformPreConfirmationDeleted(['id' => 'c1'], $this->form);

        expect($this->log)->toHaveCount(2)
            ->and($this->log[0])->toBeInstanceOf(NotificationDeleted::class)
            ->and($this->log[0]->notification)->toBe(['id' => 'n1'])
            ->and($this->log[0]->form)->toBe($this->form)
            ->and($this->log[1])->toBeInstanceOf(ConfirmationDeleted::class)
            ->and($this->log[1]->confirmation)->toBe(['id' => 'c1'])
            ->and($this->log[1]->form)->toBe($this->form);
    });

    it('dispatches form lifecycle events with the form loaded from its id', function (string $handler, string $expected): void {
        $this->dispatcher->{$handler}(3);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf($expected)
            ->and($this->log[0]->form)->toBe($this->form);
    })->with([
        'before delete' => ['handleGformBeforeDeleteForm', FormDeleted::class],
        'trashed' => ['handleGformPostFormTrashed', FormTrashed::class],
        'restored' => ['handleGformPostFormRestored', FormRestored::class],
    ]);

    it('ignores the lifecycle of a form that cannot be loaded', function (string $handler): void {
        $this->dispatcher->{$handler}(404);

        expect($this->log)->toHaveCount(0);
    })->with(['handleGformBeforeDeleteForm', 'handleGformPostFormTrashed', 'handleGformPostFormRestored']);

    it('dispatches EntryDeleted with the entry loaded from its id', function (): void {
        $this->dispatcher->handleGformDeleteLead(11);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(EntryDeleted::class)
            ->and($this->log[0]->entry)->toBe($this->entry);
    });

    it('dispatches EntryNoteAdded with every detail of the note', function (): void {
        $this->dispatcher->handleGformPostNoteAdded(5, 11, 2, 'Jane', 'Called back', 'user');

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(EntryNoteAdded::class)
            ->and($this->log[0]->noteId)->toBe(5)
            ->and($this->log[0]->entry)->toBe($this->entry)
            ->and($this->log[0]->userId)->toBe(2)
            ->and($this->log[0]->userName)->toBe('Jane')
            ->and($this->log[0]->note)->toBe('Called back')
            ->and($this->log[0]->noteType)->toBe('user');
    });

    it('dispatches EntryStatusUpdated with the new and the previous status', function (): void {
        $this->dispatcher->handleGformUpdateStatus(11, 'trash', 'active');

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(EntryStatusUpdated::class)
            ->and($this->log[0]->entry)->toBe($this->entry)
            ->and($this->log[0]->status)->toBe('trash')
            ->and($this->log[0]->previousStatus)->toBe('active');
    });

    it('ignores entry events for an entry that cannot be loaded', function (): void {
        $this->dispatcher->handleGformDeleteLead(404);
        $this->dispatcher->handleGformPostNoteAdded(5, 404, 2, 'Jane', 'Note', 'user');
        $this->dispatcher->handleGformUpdateStatus(404, 'trash');

        expect($this->log)->toHaveCount(0);
    });
});
