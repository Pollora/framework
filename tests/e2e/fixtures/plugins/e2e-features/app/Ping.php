<?php

declare(strict_types=1);

namespace Plugin\E2eFeatures;

use Pollora\Ajax\Domain\Model\AjaxAccess;
use Pollora\Attributes\Ajax;

/**
 * An admin-ajax action declared by attribute, open to visitors and to logged-in users.
 *
 * It only answers a ping, so it takes no nonce, and says so: pollora/ajax 2.0 verifies one by default.
 */
class Ping
{
    #[Ajax('e2e_ping', access: AjaxAccess::ALL, nonce: false)]
    public function ping(): void
    {
        wp_send_json_success(['pong' => true, 'loggedIn' => is_user_logged_in()]);
    }
}
