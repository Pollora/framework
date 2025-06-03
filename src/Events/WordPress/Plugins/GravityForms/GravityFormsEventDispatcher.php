<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms;

use Pollora\Events\WordPress\AbstractEventDispatcher;
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
        'gform_pre_confirmation_save',
        'gform_pre_notification_save',
        'gform_pre_notification_deleted',
        'gform_pre_confirmation_deleted',
        'gform_confirmation_status',
        'gform_notification_status',
        'gform_post_export_entries',
        'gform_forms_post_import',
        'gform_delete_lead',
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
     * @param  array  $notification  Notification data
     * @param  array  $form  Form data
     */
    public function handleGformPreNotificationDeleted(array $notification, array $form): void
    {
        $this->dispatch(NotificationDeleted::class, [$notification, $form]);
    }

    /**
     * Handle confirmation deletion event.
     *
     * @param  array  $confirmation  Confirmation data
     * @param  array  $form  Form data
     */
    public function handleGformPreConfirmationDeleted(array $confirmation, array $form): void
    {
        $this->dispatch(ConfirmationDeleted::class, [$confirmation, $form]);
    }

    /**
     * Handle form deletion event.
     *
     * @param  int  $form_id  Form ID
     */
    public function handleGformBeforeDeleteForm(int $form_id): void
    {
        $form = \GFAPI::get_form($form_id);
        if ($form) {
            $this->dispatch(FormDeleted::class, [$form]);
        }
    }

    /**
     * Handle form trash event.
     *
     * @param  int  $form_id  Form ID
     */
    public function handleGformPostFormTrashed(int $form_id): void
    {
        $form = \GFAPI::get_form($form_id);
        if ($form) {
            $this->dispatch(FormTrashed::class, [$form]);
        }
    }

    /**
     * Handle form restore event.
     *
     * @param  int  $form_id  Form ID
     */
    public function handleGformPostFormRestored(int $form_id): void
    {
        $form = \GFAPI::get_form($form_id);
        if ($form) {
            $this->dispatch(FormRestored::class, [$form]);
        }
    }

    /**
     * Handle form entry deletion event.
     *
     * @param  int  $lead_id  Entry ID
     */
    public function handleGformDeleteLead(int $lead_id): void
    {
        $entry = \GFAPI::get_entry($lead_id);
        if ($entry) {
            $this->dispatch(EntryDeleted::class, [$entry]);
        }
    }

    /**
     * Handle entry note addition event.
     *
     * @param  int  $note_id  Note ID
     * @param  int  $lead_id  Entry ID
     * @param  int  $user_id  User ID
     * @param  string  $user_name  User name
     * @param  string  $note  Note content
     * @param  string  $note_type  Note type
     */
    public function handleGformPostNoteAdded(
        int $note_id,
        int $lead_id,
        int $user_id,
        string $user_name,
        string $note,
        string $note_type
    ): void {
        $entry = \GFAPI::get_entry($lead_id);
        if ($entry) {
            $this->dispatch(EntryNoteAdded::class, [
                $note_id,
                $entry,
                $user_id,
                $user_name,
                $note,
                $note_type,
            ]);
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
        $entry = \GFAPI::get_entry($lead_id);
        if ($entry) {
            $this->dispatch(EntryStatusUpdated::class, [$entry, $status, $prev]);
        }
    }
}
