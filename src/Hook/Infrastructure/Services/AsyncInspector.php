<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionResolverInterface;
use Pollora\Hook\Async\Async;
use Pollora\Hook\Async\AsyncPayload;
use Pollora\Hook\Async\PayloadStore;
use Pollora\Hook\Async\QueuedHandler;
use Pollora\Hook\Async\UniqueLock;

/**
 * Reads what asynchronous actions leave behind: the registrations, the
 * WP-Cron events, the stored payloads and locks, the queued jobs.
 *
 * It only reads. Every method answers for one request: the hooks registered
 * so far, the rows as they stand.
 */
class AsyncInspector
{
    /** Rows read per query: enough to notice a problem, cheap enough for Site Health. */
    private const int BATCH = 500;

    public function __construct(
        private readonly Repository $config,
        private readonly ConnectionResolverInterface $databases,
    ) {}

    /**
     * The asynchronous registrations, wherever they were made: ->async() or #[Async].
     *
     * @return list<QueuedHandler>
     */
    public function registrations(): array
    {
        global $wp_filter;

        $registrations = [];

        foreach ((array) $wp_filter as $hook) {
            foreach (is_object($hook) && isset($hook->callbacks) ? (array) $hook->callbacks : [] as $callbacks) {
                foreach ((array) $callbacks as $callback) {
                    if (($callback['function'] ?? null) instanceof QueuedHandler) {
                        $registrations[] = $callback['function'];
                    }
                }
            }
        }

        return $registrations;
    }

    /**
     * Whether WordPress runs WP-Cron on page loads. Pollora turns it off: a system cron must request wp-cron.php.
     */
    public function cronRunsOnPageLoad(): bool
    {
        return ! defined('DISABLE_WP_CRON') || ! constant('DISABLE_WP_CRON');
    }

    /**
     * WP-Cron events due for longer than that: nothing runs wp-cron.php.
     *
     * @return array{count: int, oldest: int|null} Count, and the timestamp of the oldest one
     */
    public function overdueCronEvents(int $seconds): array
    {
        if (! function_exists('_get_cron_array')) {
            return ['count' => 0, 'oldest' => null];
        }

        $threshold = time() - $seconds;
        $count = 0;
        $oldest = null;

        foreach ((array) _get_cron_array() as $timestamp => $hooks) {
            if (! is_int($timestamp) || $timestamp >= $threshold) {
                continue;
            }

            foreach ((array) $hooks as $events) {
                $count += count((array) $events);
            }

            $oldest = $oldest === null ? $timestamp : min($oldest, $timestamp);
        }

        return ['count' => $count, 'oldest' => $oldest];
    }

    /**
     * Stored payloads dispatched longer ago than that, that no event will run:
     * the daily recovery task did not schedule them again.
     */
    public function strandedPayloads(int $seconds): int
    {
        $threshold = time() - $seconds;
        $stranded = 0;

        foreach ($this->options(PayloadStore::OPTION_PREFIX) as $name => $value) {
            $id = substr($name, strlen(PayloadStore::OPTION_PREFIX));

            try {
                $dispatchedAt = (new \DateTimeImmutable(AsyncPayload::fromJson($value)->origin['dispatchedAt']))->getTimestamp();
            } catch (\Throwable) {
                $stranded++;

                continue;
            }

            if ($dispatchedAt < $threshold && ! $this->isPending($id)) {
                $stranded++;
            }
        }

        return $stranded;
    }

    /**
     * Unique locks expired longer ago than that: the daily maintenance task does not run.
     */
    public function expiredLocks(int $seconds): int
    {
        $threshold = time() - $seconds;

        return count(array_filter($this->options(UniqueLock::OPTION_PREFIX), static fn (string $expiresAt): bool => (int) $expiresAt < $threshold));
    }

    /**
     * Jobs of a database queue connection available for longer than that and never picked up: no worker runs.
     *
     * @return array{count: int, oldest: int|null}|null Null when the connection does not keep its jobs in a table
     */
    public function waitingJobs(string $connection, int $seconds): ?array
    {
        $settings = $this->config->get('queue.connections.'.$connection);

        if (! is_array($settings) || ($settings['driver'] ?? null) !== 'database') {
            return null;
        }

        $jobs = $this->databases->connection($settings['connection'] ?? null)
            ->table($settings['table'] ?? 'jobs')
            ->whereNull('reserved_at')
            ->where('available_at', '<', time() - $seconds);

        $count = (clone $jobs)->count();

        return ['count' => $count, 'oldest' => $count > 0 ? (int) $jobs->min('available_at') : null];
    }

    /**
     * @return array<string, string> Option values, by name
     */
    private function options(string $prefix): array
    {
        global $wpdb;

        if (! is_object($wpdb)) {
            return [];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d",
            $wpdb->esc_like($prefix).'%',
            self::BATCH,
        ), 'ARRAY_A');

        $options = [];
        foreach ((array) $rows as $row) {
            $options[(string) $row['option_name']] = (string) $row['option_value'];
        }

        return $options;
    }

    private function isPending(string $id): bool
    {
        if (function_exists('wp_next_scheduled') && wp_next_scheduled(Async::HOOK, [$id]) !== false) {
            return true;
        }

        return function_exists('as_has_scheduled_action') && as_has_scheduled_action(Async::HOOK, [$id]);
    }
}
