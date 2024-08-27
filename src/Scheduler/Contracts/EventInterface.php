<?php

declare(strict_types=1);

namespace Pollen\Scheduler\Contracts;

interface EventInterface
{
    public function __construct(?object $event = null);

    public function getHook(): string;

    public function getTimestamp(): int;

    public function getArgs(): array;

    public function handle(): void;

    public static function createJob(object $event): self;
}
