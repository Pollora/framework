<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\GravityForms\Notification;

use Pollora\Events\WordPress\Plugins\GravityForms\GravityFormsEvent;

/**
 * Base class for all Gravity Forms notification related events.
 *
 * This abstract class provides the foundation for all notification events,
 * containing the notification data and the associated form.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
abstract class NotificationEvent extends GravityFormsEvent
{
    /**
     * Constructor.
     *
     * @param array $notification The notification data
     * @param array $form The form data
     */
    public function __construct(
        public readonly array $notification,
        array $form
    ) {
        parent::__construct($form);
    }
} 