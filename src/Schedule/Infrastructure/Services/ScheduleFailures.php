<?php

declare(strict_types=1);

namespace Pollora\Schedule\Infrastructure\Services;

/**
 * Events of the "laravel" driver that failed for good, kept for a week so
 * pollora:doctor can say so: the job is in failed_jobs, but a single event is
 * out of the index by then and nothing else shows it.
 */
final class ScheduleFailures
{
    public const string OPTION = 'pollora_schedule_failures';

    private const int KEEP = 20;

    private const int WEEK = 604800;

    public static function record(string $hook, string $message): void
    {
        $failures = self::recent();
        $failures[] = ['hook' => $hook, 'time' => time(), 'message' => mb_substr($message, 0, 300)];

        update_option(self::OPTION, array_slice($failures, -self::KEEP), false);
    }

    /**
     * Failures of the last week, oldest first.
     *
     * @return list<array{hook: string, time: int, message: string}>
     */
    public static function recent(): array
    {
        $stored = get_option(self::OPTION, []);
        $recent = [];

        foreach (is_array($stored) ? $stored : [] as $failure) {
            if (is_array($failure) && is_string($failure['hook'] ?? null) && is_int($failure['time'] ?? null) && $failure['time'] > time() - self::WEEK) {
                $recent[] = ['hook' => $failure['hook'], 'time' => $failure['time'], 'message' => (string) ($failure['message'] ?? '')];
            }
        }

        return $recent;
    }
}
