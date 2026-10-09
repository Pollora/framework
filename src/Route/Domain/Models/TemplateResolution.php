<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Models;

use Pollora\Route\Domain\Enums\TemplateOutcome;

/**
 * What the template hierarchy settled on for one request.
 *
 * The frontend controller works this out and then throws it away; keeping it
 * lets tooling say which template answered without running the hierarchy a
 * second time — and a second run could disagree, since `template_include`
 * filters are free to look at anything.
 */
final readonly class TemplateResolution
{
    /**
     * @param  string  $template  The file `template_include` returned, empty when themes are off
     * @param  string|null  $condition  The conditional tag that picked the template (`is_single`…), null for the index fallback
     * @param  string|null  $view  The Blade view name, when the template maps to one
     * @param  bool  $usedIndexFallback  Whether no specific template matched and the index answered
     * @param  TemplateOutcome  $outcome  What finally rendered the response
     */
    public function __construct(
        public string $template,
        public ?string $condition,
        public ?string $view,
        public bool $usedIndexFallback,
        public TemplateOutcome $outcome,
    ) {}
}
