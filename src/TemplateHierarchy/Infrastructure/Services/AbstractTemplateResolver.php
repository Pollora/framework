<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Infrastructure\Services;

use Pollora\TemplateHierarchy\Domain\Contracts\TemplateResolverInterface;
use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;

/**
 * Abstract base class for template resolvers.
 */
abstract class AbstractTemplateResolver implements TemplateResolverInterface
{
    /**
     * The origin of templates from this resolver.
     */
    protected string $origin = 'unknown';

    /**
     * Creates a template candidate.
     */
    protected function createCandidate(string $templatePath, string $type = 'php', int $priority = 10): TemplateCandidate
    {
        return new TemplateCandidate($type, $templatePath, $this->origin, $priority);
    }

    /**
     * Creates a PHP template candidate.
     */
    protected function createPhpCandidate(string $templatePath, int $priority = 10): TemplateCandidate
    {
        return $this->createCandidate($templatePath, 'php', $priority);
    }

    /**
     * Creates a Blade template candidate.
     */
    protected function createBladeCandidate(string $templateName, int $priority = 10): TemplateCandidate
    {
        return $this->createCandidate($templateName, 'blade', $priority);
    }

    /**
     * Creates a PHP and Blade candidate for the same template.
     *
     * @return TemplateCandidate[]
     */
    protected function createPhpAndBladeCandidates(string $templatePath, int $priority = 10): array
    {
        $bladeTemplateName = str_replace(
            ['.php', DIRECTORY_SEPARATOR],
            ['', '.'],
            $templatePath
        );

        return [
            $this->createPhpCandidate($templatePath, $priority),
            $this->createBladeCandidate($bladeTemplateName, $priority + 1),
        ];
    }

    /**
     * Get the queried object with caching.
     */
    protected function getQueriedObject(): ?object
    {
        static $queriedObject = null;

        if ($queriedObject === null) {
            $queriedObject = get_queried_object();
        }

        return $queriedObject;
    }
}
