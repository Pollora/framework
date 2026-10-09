<?php

declare(strict_types=1);

namespace Pollora\Route\Application\Services;

use Pollora\Route\Domain\Models\TemplateResolution;

/**
 * Remembers which template answered the current request.
 *
 * Filled by the frontend controller, read by debugging tools. A request that a
 * `Route::wp()` route or a Laravel route answered never reaches the template
 * hierarchy, so it leaves this empty — which is how a reader tells them apart.
 */
final class AnsweringTemplate
{
    private ?TemplateResolution $resolution = null;

    public function record(TemplateResolution $resolution): void
    {
        $this->resolution = $resolution;
    }

    public function resolution(): ?TemplateResolution
    {
        return $this->resolution;
    }

    /**
     * Forget the last resolution, for workers that serve several requests.
     */
    public function reset(): void
    {
        $this->resolution = null;
    }
}
