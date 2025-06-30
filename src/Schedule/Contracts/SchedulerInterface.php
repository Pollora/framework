<?php

declare(strict_types=1);

namespace Pollora\Schedule\Contracts;

/**
 * Interface for WordPress cron scheduler functionality.
 *
 * Defines the contract for managing WordPress cron events with improved
 * type safety and integration with Laravel's queue system.
 */
interface SchedulerInterface
{
    /**
     * Filter the cron option before update.
     *
     * @param  array  $value  New cron option value
     * @param  array  $old_value  Previous cron option value
     * @return array Modified cron array
     */
    public function preUpdateOptionCron(array $value, array $old_value): array;

    /**
     * Filter the cron option value.
     *
     * @param  mixed  $value  Current option value
     * @return array Cron jobs array
     */
    public function preOptionCron($value): array;

    /**
     * Filter event scheduling.
     *
     * @param  mixed  $pre  Current filtered value
     * @param  object  $event  Event to schedule
     * @param  bool  $wp_error  Whether to return WP_Error on failure
     * @return mixed Scheduled event or error
     */
    public function preScheduleEvent($pre, object $event, bool $wp_error);

    /**
     * Filter event rescheduling.
     *
     * @param  mixed  $pre  Current filtered value
     * @param  object  $event  Event to reschedule
     * @param  bool  $wp_error  Whether to return WP_Error on failure
     * @return mixed Rescheduled event or error
     */
    public function preRescheduleEvent($pre, object $event, bool $wp_error);

    /**
     * Filter event unscheduling.
     *
     * @param  mixed  $pre  Current filtered value
     * @param  int  $timestamp  Event timestamp
     * @param  string  $hook  Event hook
     * @param  array  $args  Event arguments
     * @param  bool  $wp_error  Whether to return WP_Error on failure
     * @return mixed Unschedule result or error
     */
    public function preUnscheduleEvent($pre, int $timestamp, string $hook, array $args, bool $wp_error);

    /**
     * Filter scheduled hook clearing.
     *
     * @param  mixed  $pre  Current filtered value
     * @param  string  $hook  Hook to clear
     * @param  array|null  $args  Arguments to match
     * @param  bool  $wp_error  Whether to return WP_Error on failure
     * @return mixed Clear result or error
     */
    public function preClearScheduledHook($pre, string $hook, ?array $args, bool $wp_error);

    /**
     * Filter hook unscheduling.
     *
     * @param  mixed  $pre  Current filtered value
     * @param  string  $hook  Hook to unschedule
     * @param  bool  $wp_error  Whether to return WP_Error on failure
     * @return mixed Unschedule result or error
     */
    public function preUnscheduleHook($pre, string $hook, bool $wp_error);

    /**
     * Filter scheduled event retrieval.
     *
     * @param  mixed  $pre  Current filtered value
     * @param  string  $hook  Event hook
     * @param  array  $args  Event arguments
     * @param  int|null  $timestamp  Event timestamp
     * @return mixed Scheduled event or false
     */
    public function preGetScheduledEvent($pre, string $hook, array $args, ?int $timestamp);

    /**
     * Filter ready cron jobs retrieval.
     *
     * @param  mixed  $pre  Current filtered value
     * @return array Ready cron jobs
     */
    public function preGetReadyCronJobs($pre): array;
}
