<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\Dispatcher;
use Pollora\Events\WordPress\Menu\MenuDeleted;
use Pollora\Events\WordPress\Menu\MenuEventDispatcher;
use Pollora\Hook\Domain\Contract\Action;

describe('MenuEventDispatcher', function (): void {
    beforeEach(function (): void {
        $this->events = Mockery::mock(Dispatcher::class);
        $this->dispatcher = new MenuEventDispatcher($this->events, Mockery::mock(Action::class));
    });

    it('dispatches MenuDeleted with the term WordPress passes to delete_nav_menu', function (): void {
        $menu = new WP_Term;
        $menu->term_id = 12;
        $menu->taxonomy = 'nav_menu';

        $this->events->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn (object $event): bool => $event instanceof MenuDeleted && $event->menu === $menu);

        $this->dispatcher->handleDeleteNavMenu(12, 12, $menu, []);
    });

    it('dispatches nothing when the deleted term is not a WP_Term', function (): void {
        $this->events->shouldNotReceive('dispatch');

        $this->dispatcher->handleDeleteNavMenu(12, 12, null, []);
    });
});
