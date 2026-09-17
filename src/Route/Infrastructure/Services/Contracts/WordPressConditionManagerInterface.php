<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Services\Contracts;

use Pollora\Route\Domain\Contracts\ConditionResolverInterface;

/**
 * Interface for managing WordPress condition aliases.
 */
interface WordPressConditionManagerInterface extends ConditionResolverInterface
{
    /**
     * Add a condition alias.
     */
    public function addCondition(string $alias, string $function): void;
}
