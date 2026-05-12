<?php

declare(strict_types=1);

namespace Pollora\Foundation\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;

/**
 * Wraps the original command loader to support short aliases
 * for Pollora commands when running in binary mode.
 *
 * When `POLLORA_BINARY=true`, this loader resolves short names
 * (e.g. `make-plugin`) to their full names (`pollora:make-plugin`).
 */
final readonly class PolloraBinaryCommandLoader implements CommandLoaderInterface
{
    /**
     * Reverse map: short name → original name.
     *
     * @var array<string, string>
     */
    private array $reverseMap;

    public function __construct(
        private CommandLoaderInterface $inner,
    ) {
        $this->reverseMap = array_flip(PolloraBinaryManager::getSignatureMap());
    }

    public function get(string $name): Command
    {
        $resolved = $this->resolve($name);
        $command = $this->inner->get($resolved);

        // Apply the short name + alias on load
        if ($resolved !== $name) {
            $command->setAliases(array_merge(
                $command->getAliases(),
                [$resolved]
            ));
            $command->setName($name);
        }

        return $command;
    }

    public function has(string $name): bool
    {
        return $this->inner->has($this->resolve($name));
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        $names = $this->inner->getNames();
        $map = PolloraBinaryManager::getSignatureMap();

        // Add short aliases for pollora: commands
        foreach ($map as $original => $short) {
            if (in_array($original, $names, true)) {
                $names[] = $short;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Resolve a short name to the original pollora: name.
     */
    private function resolve(string $name): string
    {
        return $this->reverseMap[$name] ?? $name;
    }
}
