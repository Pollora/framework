<?php

declare(strict_types=1);

namespace Pollora\Zzprobe;

/**
 * A throwaway class that exists only to check the mutation gate of the framework in CI. Never merged.
 */
final class Probe
{
    /**
     * Adds two integers.
     */
    public function total(int $a, int $b): int
    {
        return $a + $b;
    }

    /**
     * Whether a number is above one hundred.
     */
    public function isBig(int $n): bool
    {
        return $n > 100;
    }
}
