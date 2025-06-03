<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Application\Services;

use Illuminate\Contracts\Config\Repository;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\TemplateHierarchy\Domain\Contracts\TemplateRendererInterface;
use Pollora\TemplateHierarchy\Domain\Contracts\TemplateSourceInterface;
use Pollora\TemplateHierarchy\Domain\Exceptions\TemplateNotFoundException;
use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;

/**
 * Main service for finding and resolving templates.
 */
class TemplateFinderService
{
    /**
     * Array of registered template sources.
     *
     * @var TemplateSourceInterface[]
     */
    private array $sources = [];

    /**
     * Array of registered template renderers.
     *
     * @var TemplateRendererInterface[]
     */
    private array $renderers = [];

    /**
     * The ordered hierarchy of template candidates.
     *
     * @var TemplateCandidate[]
     */
    private array $hierarchy = [];

    /**
     * Whether the hierarchy has been built.
     */
    private bool $hierarchyBuilt = false;

    /**
     * Create a new template finder service.
     */
    public function __construct(
        private readonly Repository $config,
        private readonly Filter $filter
    ) {}

    /**
     * Register a template source.
     */
    public function registerSource(TemplateSourceInterface $source): self
    {
        $this->sources[$source->getName()] = $source;
        // Sources are sorted by priority when the hierarchy is built
        $this->hierarchyBuilt = false;

        return $this;
    }

    /**
     * Register a template renderer.
     */
    public function registerRenderer(TemplateRendererInterface $renderer): self
    {
        $this->renderers[] = $renderer;

        return $this;
    }

    /**
     * Get all template candidates for the current request.
     *
     * @param  bool  $refresh  Force refresh of the hierarchy even if already built
     * @return TemplateCandidate[] Array of template candidates
     */
    public function getHierarchy(bool $refresh = false): array
    {
        if ($refresh || ! $this->hierarchyBuilt) {
            $this->buildHierarchy();
        }

        return $this->hierarchy;
    }

    /**
     * Resolve the appropriate template for the current request.
     *
     * @param  bool  $refresh  Force refresh of the hierarchy even if already built
     * @return string The resolved template path or identifier
     *
     * @throws TemplateNotFoundException If no template could be found
     */
    public function resolveTemplate(bool $refresh = false): string
    {
        $candidates = $this->getHierarchy($refresh);

        // Allow filtering of the final candidates
        $candidates = $this->filter->apply('pollora/template_hierarchy/candidates', $candidates);

        foreach ($candidates as $candidate) {
            foreach ($this->renderers as $renderer) {
                if ($renderer->supports($candidate->type)) {
                    $resolved = $renderer->resolve($candidate);
                    if ($resolved !== null) {
                        return $resolved;
                    }
                }
            }
        }

        throw new TemplateNotFoundException('No template could be resolved for the current request.');
    }

    /**
     * Build the template hierarchy from all sources.
     */
    private function buildHierarchy(): void
    {
        $this->hierarchy = [];

        // Sort sources by priority (lower number = higher priority)
        $sortedSources = $this->sources;
        uasort($sortedSources, fn (TemplateSourceInterface $a, TemplateSourceInterface $b) => $a->getPriority() <=> $b->getPriority()
        );

        foreach ($sortedSources as $source) {
            $resolvers = $source->getResolvers();

            foreach ($resolvers as $resolver) {
                if (! $resolver->applies()) {
                    continue;
                }

                $candidates = $resolver->getCandidates();
                array_push($this->hierarchy, ...$candidates);
            }
        }

        // Allow filtering of the hierarchy
        $this->hierarchy = $this->filter->apply('pollora/template_hierarchy/hierarchy', $this->hierarchy);

        $this->hierarchyBuilt = true;
    }

    /**
     * Get template candidates filtered by type.
     *
     * @param  string  $type  The template type to filter by (e.g., 'php', 'blade')
     * @param  bool  $refresh  Force refresh of the hierarchy
     * @return TemplateCandidate[] Array of template candidates filtered by type
     */
    public function getHierarchyByType(string $type, bool $refresh = false): array
    {
        $candidates = $this->getHierarchy($refresh);

        return array_filter($candidates, function (TemplateCandidate $candidate) use ($type) {
            return $candidate->type === $type;
        });
    }

    /**
     * Get template paths extracted from all candidates.
     *
     * @param  bool  $refresh  Force refresh of the hierarchy
     * @return string[] Array of template paths
     */
    public function getTemplatePaths(bool $refresh = false): array
    {
        $candidates = $this->getHierarchy($refresh);

        return array_map(function (TemplateCandidate $candidate) {
            return $candidate->templatePath;
        }, $candidates);
    }

    /**
     * Get template paths filtered by type.
     *
     * @param  string  $type  The template type to filter by (e.g., 'php', 'blade')
     * @param  bool  $refresh  Force refresh of the hierarchy
     * @return string[] Array of template paths for the specified type
     */
    public function getTemplatePathsByType(string $type, bool $refresh = false): array
    {
        $candidates = $this->getHierarchyByType($type, $refresh);

        return array_map(function (TemplateCandidate $candidate) {
            return $candidate->templatePath;
        }, $candidates);
    }
}
