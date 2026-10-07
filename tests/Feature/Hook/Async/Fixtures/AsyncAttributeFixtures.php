<?php

declare(strict_types=1);

use Pollora\Attributes\Action as ActionAttribute;
use Pollora\Attributes\Async as AsyncAttribute;
use Pollora\Attributes\Filter as FilterAttribute;

/*
 * Hook classes for AsyncAttributeTest. Their methods are empty and some are
 * unused or broken on purpose: Rector must leave this file alone (rector.php).
 */

final class AsyncAttributeHooks
{
    #[ActionAttribute('save_post_event', priority: 20)]
    #[AsyncAttribute(delay: 60, via: 'queue', onQueue: 'integrations', unique: true, tries: 3, backoff: [5], asUser: true, keepMissing: true)]
    public function syncToCrm(int $postId): void {}

    #[ActionAttribute('save_post_event')]
    #[ActionAttribute('save_post_page')]
    #[AsyncAttribute(except: ['save_post_page'], unique: 600)]
    public function handle(int $postId): void {}

    #[ActionAttribute('transition_post_status')]
    #[AsyncAttribute(capture: 'captureTransition', when: 'isPublished')]
    public function notify(string $new, string $old): void {}

    #[ActionAttribute('wp_loaded')]
    public function synchronous(): void {}

    /**
     * @return array<string, string>
     */
    public function captureTransition(string $new, string $old): array
    {
        return ['from' => $old];
    }

    public function isPublished(string $new): bool
    {
        return $new === 'publish';
    }

    #[FilterAttribute('the_content')]
    #[AsyncAttribute]
    public function filterContent(string $content): string
    {
        return $content;
    }

    #[AsyncAttribute]
    public function orphan(): void {}
}

#[AsyncAttribute(tries: 2)]
final class AsyncAttributeClassHooks
{
    #[ActionAttribute('user_register')]
    public function welcome(int $userId): void {}

    #[ActionAttribute('profile_update')]
    #[AsyncAttribute(tries: 5)]
    public function audit(int $userId): void {}
}

final class AsyncAttributeInvalidHooks
{
    #[ActionAttribute('save_post')]
    #[AsyncAttribute(except: 'save_psot')]
    public function typo(int $postId): void {}

    #[ActionAttribute('save_post')]
    #[AsyncAttribute(capture: 'missing')]
    public function missingCapture(int $postId): void {}

    #[ActionAttribute('save_post')]
    #[AsyncAttribute(when: 'hidden')]
    public function privateCondition(int $postId): void {}

    #[ActionAttribute('save_post')]
    #[AsyncAttribute(tries: 0)]
    public function noAttempt(int $postId): void {}

    #[ActionAttribute('save_post')]
    #[AsyncAttribute(backoff: [-1])]
    public function negativeBackoff(int $postId): void {}

    #[ActionAttribute('save_post')]
    #[AsyncAttribute(unique: 0)]
    public function emptyLock(int $postId): void {}

    private function hidden(): bool
    {
        return true;
    }
}
