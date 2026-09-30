<?php

declare(strict_types=1);

namespace Pollora\Doctor\Domain\Models;

use Pollora\Doctor\Domain\Enums\CheckStatus;

/**
 * What a check found: a one-line summary, the details that prove it, and, when
 * something is wrong, what to do about it.
 */
final readonly class CheckResult
{
    /**
     * @param  list<string>  $details
     */
    private function __construct(
        public CheckStatus $status,
        public string $summary,
        public array $details = [],
        public ?string $fix = null,
    ) {}

    /**
     * @param  list<string>  $details
     */
    public static function ok(string $summary, array $details = []): self
    {
        return new self(CheckStatus::Ok, $summary, $details);
    }

    /**
     * @param  list<string>  $details
     */
    public static function warning(string $summary, array $details = [], ?string $fix = null): self
    {
        return new self(CheckStatus::Warning, $summary, $details, $fix);
    }

    /**
     * @param  list<string>  $details
     */
    public static function error(string $summary, array $details = [], ?string $fix = null): self
    {
        return new self(CheckStatus::Error, $summary, $details, $fix);
    }

    public static function skipped(string $reason): self
    {
        return new self(CheckStatus::Skipped, $reason);
    }

    /**
     * @return array{status: string, summary: string, details: list<string>, fix: ?string}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'summary' => $this->summary,
            'details' => $this->details,
            'fix' => $this->fix,
        ];
    }
}
