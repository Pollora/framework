<?php

namespace Pollora\Asset\Domain\Models;

use Pollora\Asset\Domain\Contracts\ViteManagerInterface;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;

/**
 * Domain-only (mock or stub) implementation of ViteManagerInterface.
 *
 * Use for testing or pure domain logic.
 */
class ViteManager implements ViteManagerInterface
{
    /**
     * Returns a stub asset container instance.
     *
     * @return AssetContainer
     */
    public function container(): AssetContainer { return new AssetContainer('stub', []); }

    /**
     * Returns an empty array for entry point URLs (stub).
     *
     * @param array $entrypoints
     * @return array
     */
    public function getAssetUrls(array $entrypoints): array { return []; }

    /**
     * Returns the asset path as-is (stub).
     *
     * @param string $path
     * @return string
     */
    public function asset(string $path): string { return $path; }

    /**
     * Always returns false (stub for domain logic/testing).
     *
     * @return bool
     */
    public function isRunningHot(): bool { return false; }

    /**
     * Returns an empty string for the Vite client HTML (stub).
     *
     * @return string
     */
    public function getViteClientHtml(): string { return ''; }
}
