<?php

declare(strict_types=1);

namespace Pollen\Scheduler\Contracts;

interface SchedulerInterface
{
    public function preUpdateOptionCron(array $value, array $old_value): array;

    public function preOptionCron($value): array;

    public function preScheduleEvent($pre, object $event, bool $wp_error);

    public function preRescheduleEvent($pre, object $event, bool $wp_error);

    public function preUnscheduleEvent($pre, int $timestamp, string $hook, array $args, bool $wp_error);

    public function preClearScheduledHook($pre, string $hook, ?array $args, bool $wp_error);

    public function preUnscheduleHook($pre, string $hook, bool $wp_error);

    public function preGetScheduledEvent($pre, string $hook, array $args, ?int $timestamp);

    public function preGetReadyCronJobs($pre): array;
}
