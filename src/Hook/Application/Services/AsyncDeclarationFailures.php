<?php

declare(strict_types=1);

namespace Pollora\Hook\Application\Services;

/**
 * The #[Async] declarations discovery could not honour, kept so that
 * pollora:doctor can list them instead of leaving them in the log.
 *
 * Each such method still runs, synchronously: nothing else shows the
 * declaration was ignored.
 */
final class AsyncDeclarationFailures
{
    /**
     * @var array<string, string> Reason, by method ("Class::method()")
     */
    private array $failures = [];

    public function fail(string $class, string $method, string $reason): void
    {
        $this->failures[sprintf('%s::%s()', ltrim($class, '\\'), $method)] = $reason;
    }

    /**
     * @return array<string, string> Reason, by method ("Class::method()")
     */
    public function all(): array
    {
        return $this->failures;
    }
}
