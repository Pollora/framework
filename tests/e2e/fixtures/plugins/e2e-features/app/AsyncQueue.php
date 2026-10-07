<?php

declare(strict_types=1);

namespace Plugin\E2eFeatures;

use Illuminate\Support\Facades\Request;
use Pollora\Ajax\Domain\Model\AjaxAccess;
use Pollora\Attributes\Action;
use Pollora\Attributes\Ajax;
use Pollora\Attributes\Async;
use Pollora\Hook\Async\AsyncContext;
use Psr\Log\LoggerInterface;

/**
 * An #[Action] made asynchronous by #[Async], queued as a Laravel job.
 *
 * An admin-ajax action fires the hook; the handler records what it received in
 * the e2e_async_result option, once a queue worker has run the job.
 */
class AsyncQueue
{
    #[Ajax('e2e_async_dispatch', access: AjaxAccess::ALL)]
    public function dispatch(): void
    {
        do_action('e2e_async_event', absint(Request::input('id') ?? 0));

        wp_send_json_success(['queued' => true]);
    }

    #[Action('e2e_async_event')]
    #[Async(via: 'queue')]
    public function handle(int $id, AsyncContext $context, LoggerInterface $logger): void
    {
        update_option('e2e_async_result', [
            'id' => $id,
            'hook' => $context->hook,
            'console' => app()->runningInConsole(),
            'injected' => $logger instanceof LoggerInterface,
        ], false);
    }
}
