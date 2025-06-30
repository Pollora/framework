<?php

declare(strict_types=1);

namespace Pollora\Schedule\Contracts;

/**
 * Interface for WordPress scheduled events.
 *
 * Defines the contract for handling WordPress cron events with improved
 * type safety and job queue integration.
 */
interface EventInterface
{
    /**
     * Create a new event instance.
     *
     * @param  object|null  $event  WordPress event object
     */
    public function __construct(?object $event = null);

    /**
     * Get the event hook name.
     *
     * @return string WordPress action hook
     */
    public function getHook(): string;

    /**
     * Get the event timestamp.
     *
     * @return int Unix timestamp when event should run
     */
    public function getTimestamp(): int;

    /**
     * Get the event arguments.
     *
     * @return array Arguments to pass to the hook
     */
    public function getArgs(): array;

    /**
     * Handle the event execution.
     */
    public function handle(): void;

    /**
     * Create a new job from a WordPress event.
     *
     * @param  object  $event  WordPress event object
     * @return self New event instance
     */
    public static function createJob(object $event): self;
}
