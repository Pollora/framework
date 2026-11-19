<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms;

use Illuminate\Support\Str;
use Pollora\Events\WordPress\AbstractEventDispatcher;
use Pollora\Events\WordPress\Plugins\GravityForms\Confirmation\ConfirmationCreated;
use Pollora\Events\WordPress\Plugins\GravityForms\Confirmation\ConfirmationDeleted;
use Pollora\Events\WordPress\Plugins\GravityForms\Confirmation\ConfirmationUpdated;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntriesExported;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntryDeleted;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntryNoteAdded;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntryNoteDeleted;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntryReadUpdated;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntryStarredUpdated;
use Pollora\Events\WordPress\Plugins\GravityForms\Entry\EntryStatusUpdated;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormActivated;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormCreated;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormDeactivated;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormDeleted;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormDuplicated;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormRestored;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormsImported;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormTrashed;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormUpdated;
use Pollora\Events\WordPress\Plugins\GravityForms\Form\FormViewsDeleted;
use Pollora\Events\WordPress\Plugins\GravityForms\Notification\NotificationCreated;
use Pollora\Events\WordPress\Plugins\GravityForms\Notification\NotificationDeleted;
use Pollora\Events\WordPress\Plugins\GravityForms\Notification\NotificationUpdated;

/**
 * Event dispatcher for Gravity Forms related events.
 *
 * This class handles the dispatching of Laravel events for Gravity Forms actions
 * such as form creation, updates, entries management, and more.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class GravityFormsEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'gform_after_save_form',
        'gform_pre_notification_deleted',
        'gform_pre_confirmation_deleted',
        'gform_post_export_entries',
        'gform_forms_post_import',
        'gform_delete_entry',
        'gform_post_note_added',
        'gform_pre_note_deleted',
        'gform_update_status',
        'gform_update_is_read',
        'gform_update_is_starred',
        'gform_before_delete_form',
        'gform_post_form_trashed',
        'gform_post_form_restored',
        'gform_post_form_activated',
        'gform_post_form_deactivated',
        'gform_post_form_duplicated',
        'gform_post_form_views_deleted',
    ];

    /**
     * Number of arguments to accept for the hooks that pass more than five.
     *
     * The base dispatcher accepts five arguments per hook, which would cut the
     * note type off gform_post_note_added (its sixth argument).
     *
     * @var array<string, int>
     */
    protected array $acceptedArguments = [
        'gform_post_note_added' => 6,
    ];

    /**
     * Register WordPress hooks for this dispatcher.
     */
    public function registerEvents(): void
    {
        foreach ($this->actions as $action) {
            $this->action->add($action, [$this, 'handle'.Str::studly($action)], 10, $this->acceptedArguments[$action] ?? 5);
        }
    }

    /**
     * Handle form save event.
     *
     * @param  array  $form  Form data
     * @param  bool  $is_new  Whether this is a new form
     */
    public function handleGformAfterSaveForm(array $form, bool $is_new): void
    {
        if ($is_new) {
            $this->dispatch(FormCreated::class, [$form]);
        } else {
            $this->dispatch(FormUpdated::class, [$form]);
        }
    }

    /**
     * Handle form confirmation save event.
     *
     * Not registered: gform_pre_confirmation_save is a filter, which must return
     * the confirmation, and this handler returns nothing.
     *
     * @param  array  $confirmation  Confirmation data
     * @param  array  $form  Form data
     * @param  bool  $is_new  Whether this is a new confirmation
     */
    public function handleGformPreConfirmationSave(array $confirmation, array $form, bool $is_new = true): void
    {
        if ($is_new) {
            $this->dispatch(ConfirmationCreated::class, [$confirmation, $form]);
        } else {
            $this->dispatch(ConfirmationUpdated::class, [$confirmation, $form]);
        }
    }

    /**
     * Handle form notification save event.
     *
     * Not registered: gform_pre_notification_save is a filter, which must return
     * the notification, and this handler returns nothing.
     *
     * @param  array  $notification  Notification data
     * @param  array  $form  Form data
     * @param  bool  $is_new  Whether this is a new notification
     */
    public function handleGformPreNotificationSave(array $notification, array $form, bool $is_new = true): void
    {
        if ($is_new) {
            $this->dispatch(NotificationCreated::class, [$notification, $form]);
        } else {
            $this->dispatch(NotificationUpdated::class, [$notification, $form]);
        }
    }

    /**
     * Handle notification deletion event.
     *
     * @param  array|null  $notification  Notification data (null when it does not exist)
     * @param  array  $form  Form data
     */
    public function handleGformPreNotificationDeleted(?array $notification, array $form): void
    {
        if ($notification !== null) {
            $this->dispatch(NotificationDeleted::class, [$notification, $form]);
        }
    }

    /**
     * Handle confirmation deletion event.
     *
     * @param  array|null  $confirmation  Confirmation data (null when it does not exist)
     * @param  array  $form  Form data
     */
    public function handleGformPreConfirmationDeleted(?array $confirmation, array $form): void
    {
        if ($confirmation !== null) {
            $this->dispatch(ConfirmationDeleted::class, [$confirmation, $form]);
        }
    }

    /**
     * Handle form deletion event.
     *
     * @param  int  $form_id  Form ID
     */
    public function handleGformBeforeDeleteForm(int $form_id): void
    {
        $this->dispatchForm(FormDeleted::class, $form_id);
    }

    /**
     * Handle form trash event.
     *
     * @param  int  $form_id  Form ID
     */
    public function handleGformPostFormTrashed(int $form_id): void
    {
        $this->dispatchForm(FormTrashed::class, $form_id);
    }

    /**
     * Handle form restore event.
     *
     * @param  int  $form_id  Form ID
     */
    public function handleGformPostFormRestored(int $form_id): void
    {
        $this->dispatchForm(FormRestored::class, $form_id);
    }

    /**
     * Handle form activation event.
     *
     * @param  int  $form_id  Form ID
     */
    public function handleGformPostFormActivated(int $form_id): void
    {
        $this->dispatchForm(FormActivated::class, $form_id);
    }

    /**
     * Handle form deactivation event.
     *
     * @param  int  $form_id  Form ID
     */
    public function handleGformPostFormDeactivated(int $form_id): void
    {
        $this->dispatchForm(FormDeactivated::class, $form_id);
    }

    /**
     * Handle form views deletion event.
     *
     * Gravity Forms also deletes the views when it deletes a form, after the form
     * is gone: the form can no longer be loaded then, and no event is dispatched.
     *
     * @param  int  $form_id  Form ID
     */
    public function handleGformPostFormViewsDeleted(int $form_id): void
    {
        $this->dispatchForm(FormViewsDeleted::class, $form_id);
    }

    /**
     * Handle form duplication event.
     *
     * @param  int  $form_id  Original form ID
     * @param  int  $new_id  Duplicated form ID
     */
    public function handleGformPostFormDuplicated(int $form_id, int $new_id): void
    {
        $form = \GFAPI::get_form($form_id);
        $duplicate = \GFAPI::get_form($new_id);

        if (is_array($form) && is_array($duplicate)) {
            $this->dispatch(FormDuplicated::class, [$form, $duplicate]);
        }
    }

    /**
     * Handle forms import event.
     *
     * @param  array  $forms  Imported forms
     */
    public function handleGformFormsPostImport(array $forms): void
    {
        $this->dispatch(FormsImported::class, [$forms]);
    }

    /**
     * Handle entries export event.
     *
     * @param  array  $form  Form data
     * @param  string|null  $start_date  Start date of the export
     * @param  string|null  $end_date  End date of the export
     * @param  array|string|null  $fields  Exported fields
     * @param  string|null  $export_id  Unique ID of the export
     */
    public function handleGformPostExportEntries(
        array $form,
        ?string $start_date = '',
        ?string $end_date = '',
        array|string|null $fields = [],
        ?string $export_id = ''
    ): void {
        $this->dispatch(EntriesExported::class, [$form, (string) $start_date, (string) $end_date, $fields ?? [], (string) $export_id]);
    }

    /**
     * Handle form entry deletion event.
     *
     * Registered on gform_delete_entry: Gravity Forms triggers a deprecation notice
     * as soon as something listens to gform_delete_lead.
     *
     * @param  int  $entry_id  Entry ID
     */
    public function handleGformDeleteEntry(int $entry_id): void
    {
        $entry = $this->loadEntry($entry_id);
        if ($entry !== null) {
            $this->dispatch(EntryDeleted::class, [$entry]);
        }
    }

    /**
     * Handle form entry deletion event (deprecated hook, no longer registered).
     *
     * @param  int  $lead_id  Entry ID
     */
    public function handleGformDeleteLead(int $lead_id): void
    {
        $this->handleGformDeleteEntry($lead_id);
    }

    /**
     * Handle entry note addition event.
     *
     * @param  int  $note_id  Note ID
     * @param  int|string|null  $lead_id  Entry ID
     * @param  int|string|null  $user_id  User ID
     * @param  string|null  $user_name  User name
     * @param  string|null  $note  Note content
     * @param  string|null  $note_type  Note type
     */
    public function handleGformPostNoteAdded(
        int $note_id,
        int|string|null $lead_id,
        int|string|null $user_id,
        ?string $user_name,
        ?string $note,
        ?string $note_type = 'user'
    ): void {
        $entry = $this->loadEntry($lead_id);
        if ($entry !== null) {
            $this->dispatch(EntryNoteAdded::class, [
                $note_id,
                $entry,
                (int) $user_id,
                (string) $user_name,
                (string) $note,
                (string) $note_type,
            ]);
        }
    }

    /**
     * Handle entry note deletion event.
     *
     * @param  int|string|null  $note_id  Note ID
     * @param  int|string|null  $lead_id  Entry ID (null when the note does not exist)
     */
    public function handleGformPreNoteDeleted(int|string|null $note_id, int|string|null $lead_id): void
    {
        $entry = $this->loadEntry($lead_id);
        if ($entry !== null) {
            $this->dispatch(EntryNoteDeleted::class, [(int) $note_id, $entry]);
        }
    }

    /**
     * Handle entry status update event.
     *
     * @param  int  $lead_id  Entry ID
     * @param  string  $status  New status
     * @param  string  $prev  Previous status
     */
    public function handleGformUpdateStatus(int $lead_id, string $status, string $prev = ''): void
    {
        $entry = $this->loadEntry($lead_id);
        if ($entry !== null) {
            $this->dispatch(EntryStatusUpdated::class, [$entry, $status, $prev]);
        }
    }

    /**
     * Handle entry read property update event.
     *
     * @param  int  $lead_id  Entry ID
     * @param  bool|int|string|null  $value  New value
     * @param  bool|int|string|null  $previous  Previous value
     */
    public function handleGformUpdateIsRead(int $lead_id, bool|int|string|null $value = null, bool|int|string|null $previous = null): void
    {
        $entry = $this->loadEntry($lead_id);
        if ($entry !== null) {
            $this->dispatch(EntryReadUpdated::class, [$entry, (bool) $value, (bool) $previous]);
        }
    }

    /**
     * Handle entry starred property update event.
     *
     * @param  int  $lead_id  Entry ID
     * @param  bool|int|string|null  $value  New value
     * @param  bool|int|string|null  $previous  Previous value
     */
    public function handleGformUpdateIsStarred(int $lead_id, bool|int|string|null $value = null, bool|int|string|null $previous = null): void
    {
        $entry = $this->loadEntry($lead_id);
        if ($entry !== null) {
            $this->dispatch(EntryStarredUpdated::class, [$entry, (bool) $value, (bool) $previous]);
        }
    }

    /**
     * Dispatch a form event with the form loaded from its ID, if it still exists.
     *
     * @param  class-string  $event  The event class name
     * @param  int  $form_id  Form ID
     */
    private function dispatchForm(string $event, int $form_id): void
    {
        $form = \GFAPI::get_form($form_id);
        if (is_array($form)) {
            $this->dispatch($event, [$form]);
        }
    }

    /**
     * Load an entry by ID.
     *
     * GFAPI::get_entry() returns a WP_Error, not false, when the entry does not exist.
     *
     * @return array<mixed>|null
     */
    private function loadEntry(int|string|null $entry_id): ?array
    {
        if ($entry_id === null) {
            return null;
        }

        $entry = \GFAPI::get_entry($entry_id);

        return is_array($entry) ? $entry : null;
    }
}
