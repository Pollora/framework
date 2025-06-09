<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Domain\Contracts;

/**
 * Interface for discovery scouts.
 *
 * Scouts are responsible for defining discovery rules and criteria
 * for specific types of classes within the application.
 */
interface ScoutInterface
{
    /**
     * Get the directories to scan.
     *
     * @return array<string> Directory or array of directories to scan
     */
    public function getDirectories(): array;

    /**
     * Get the type identifier for discovered classes.
     */
    public function getType(): string;

    /**
     * Discover classes based on the scout's criteria.
     *
     * @return array<string> Array of discovered class names
     */
    public function discover(): array;
}
