<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Services\Contracts;

/**
 * Interface for managing WordPress condition aliases.
 */
interface WordPressConditionManagerInterface
{
    /**
     * Get all WordPress condition aliases.
     * 
     * @return array<string, string>
     */
    public function getConditions(): array;

    /**
     * Resolve a condition alias to the actual WordPress function.
     */
    public function resolveCondition(string $condition): string;

    /**
     * Add a condition alias.
     */
    public function addCondition(string $alias, string $function): void;
}