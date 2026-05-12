<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\Dispatcher;
use Pollora\Events\WordPress\AbstractEventDispatcher;
use Pollora\Hook\Domain\Contracts\Action;

// Concrete test implementation
class TestEventDispatcher extends AbstractEventDispatcher
{
    protected array $actions = ['save_post', 'delete_post', 'wp_insert_comment'];

    public function handleSavePost(int $postId): void
    {
        $this->dispatch(TestSavePostEvent::class, [$postId]);
    }

    public function handleDeletePost(int $postId): void
    {
        $this->dispatch(TestDeletePostEvent::class, [$postId]);
    }

    public function handleWpInsertComment(int $commentId): void
    {
        $this->dispatch(TestCommentEvent::class, [$commentId]);
    }
}

class TestSavePostEvent
{
    public function __construct(public int $postId) {}
}

class TestDeletePostEvent
{
    public function __construct(public int $postId) {}
}

class TestCommentEvent
{
    public function __construct(public int $commentId) {}
}

describe('AbstractEventDispatcher', function (): void {
    beforeEach(function (): void {
        $this->events = Mockery::mock(Dispatcher::class);
        $this->action = Mockery::mock(Action::class);
        $this->dispatcher = new TestEventDispatcher($this->events, $this->action);
    });

    it('registers all defined actions with WordPress', function (): void {
        $this->action->shouldReceive('add')
            ->with('save_post', [$this->dispatcher, 'handleSavePost'], 10, 5)
            ->once();
        $this->action->shouldReceive('add')
            ->with('delete_post', [$this->dispatcher, 'handleDeletePost'], 10, 5)
            ->once();
        $this->action->shouldReceive('add')
            ->with('wp_insert_comment', [$this->dispatcher, 'handleWpInsertComment'], 10, 5)
            ->once();

        $this->dispatcher->registerEvents();
    });

    it('dispatches Laravel events from WordPress action handlers', function (): void {
        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn ($event): bool => $event instanceof TestSavePostEvent && $event->postId === 42));

        $this->dispatcher->handleSavePost(42);
    });

    it('dispatches delete event with correct payload', function (): void {
        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn ($event): bool => $event instanceof TestDeletePostEvent && $event->postId === 99));

        $this->dispatcher->handleDeletePost(99);
    });

    it('converts snake_case action names to StudlyCase handler methods', function (): void {
        // wp_insert_comment → handleWpInsertComment
        $this->action->shouldReceive('add')
            ->with('wp_insert_comment', [$this->dispatcher, 'handleWpInsertComment'], 10, 5)
            ->once();
        $this->action->shouldReceive('add')->withAnyArgs(); // other hooks

        $this->dispatcher->registerEvents();
    });
});
