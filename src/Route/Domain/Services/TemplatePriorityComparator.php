<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Services;

use Pollora\Route\Domain\Contracts\TemplateResolverInterface;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Domain\Models\TemplateHierarchy;

/**
 * Service for fine-grained comparison between templates and routes
 *
 * Provides detailed priority comparison logic to determine whether
 * a template should override a route based on specificity scoring.
 */
final class TemplatePriorityComparator
{
    /**
     * Configuration for comparison thresholds and weights
     */
    private const DEFAULT_CONFIG = [
        'template_existence_bonus' => 200,      // Increased bonus for existing templates
        'route_parameter_weight' => 25,         // Reduced route parameter weight
        'template_depth_weight' => 50,          // Increased template specificity weight
        'template_specificity_multiplier' => 2, // Multiplier for template specificity
        'route_condition_weight' => 0.5,        // Reduce route condition impact
        'same_specificity_prefers_template' => true,
        'laravel_route_override_threshold' => 1500,
        'debug_comparison' => false,
    ];

    public function __construct(
        private readonly TemplateResolverInterface $templateResolver,
        private readonly array $config = []
    ) {
    }

    /**
     * Compare template hierarchy to route with detailed analysis
     *
     * @param TemplateHierarchy $template The template hierarchy to compare
     * @param Route $route The route to compare against
     * @param array $context Additional context for comparison
     * @return ComparisonResult The detailed comparison result
     */
    public function compareTemplateToRoute(
        TemplateHierarchy $template,
        Route $route,
        array $context = []
    ): ComparisonResult {
        // Laravel routes have special handling
        if (!$route->isWordPressRoute()) {
            return $this->compareLaravelRoute($template, $route, $context);
        }

        // Calculate detailed scores
        $templateScore = $this->calculateTemplateScore($template, $context);

        $routeScore = $this->calculateRouteScore($route, $context);

        // Determine winner with detailed reasoning
        $templateWins = $this->determineWinner($templateScore, $routeScore, $template, $route);

        return new ComparisonResult(
            templateWins: $templateWins,
            templateScore: $templateScore,
            routeScore: $routeScore,
            reasoning: $this->buildReasoning($templateScore, $routeScore, $template, $route),
            debugInfo: $this->getDebugInfo($template, $route, $context)
        );
    }

    /**
     * Quick check if template should override route (backward compatibility)
     *
     * @param TemplateHierarchy $template The template hierarchy
     * @param Route $route The route to check
     * @param array $context Additional context
     * @return bool True if template should override
     */
    public function shouldTemplateOverrideRoute(
        TemplateHierarchy $template,
        Route $route,
        array $context = []
    ): bool {
        return $this->compareTemplateToRoute($template, $route, $context)->templateWins();
    }

    /**
     * Calculate template specificity score
     *
     * @param TemplateHierarchy $template The template hierarchy
     * @param array $context Request context
     * @return TemplateScore The calculated score with details
     */
    public function calculateTemplateScore(TemplateHierarchy $template, array $context = []): TemplateScore
    {
        $baseScore = $template->getPriority();
        $bonuses = [];

        // Apply specificity multiplier to base template score
        $specificityMultiplier = $this->getConfig('template_specificity_multiplier');
        $multipliedScore = $baseScore * $specificityMultiplier;
        $bonuses['specificity_multiplier'] = $multipliedScore - $baseScore;
        $baseScore = $multipliedScore;

        // Check if the most specific template actually exists
        $mostSpecificTemplate = $template->getPrimaryTemplate();
        $templateExists = false;

        if ($mostSpecificTemplate) {
            $templateExists = $this->templateResolver->templateExists($mostSpecificTemplate . '.blade.php');
            if (!$templateExists) {
                $templateExists = $this->templateResolver->templateExists($mostSpecificTemplate . '.php');
            }
        }

        if ($templateExists) {
            $existenceBonus = $this->getConfig('template_existence_bonus');
            $bonuses['existence'] = $existenceBonus;
            $baseScore += $existenceBonus;
        }

        // Template depth bonus (more specific templates in hierarchy)
        $templateCount = count($template->getTemplatesInOrder());
        if ($templateCount > 1) {
            $depthBonus = ($templateCount - 1) * $this->getConfig('template_depth_weight');
            $bonuses['depth'] = $depthBonus;
            $baseScore += $depthBonus;
        }

        // Context-specific bonuses
        $contextBonuses = $this->calculateContextBonuses($template, $context);
        $bonuses = array_merge($bonuses, $contextBonuses);
        $baseScore += array_sum($contextBonuses);

        if (!$templateExists) {
            $baseScore = 0;
            $bonuses = [];
        }

        return new TemplateScore(
            baseScore: $template->getPriority(),
            totalScore: $baseScore,
            bonuses: $bonuses,
            templateExists: $templateExists,
            primaryTemplate: $mostSpecificTemplate,
            templateCount: $templateCount
        );
    }

    /**
     * Calculate route specificity score
     *
     * @param Route $route The route to score
     * @param array $context Request context
     * @return RouteScore The calculated score with details
     */
    public function calculateRouteScore(Route $route, array $context = []): RouteScore
    {
        $baseScore = $route->getPriority();
        $bonuses = [];

        // WordPress route specificity (reduced impact)
        if ($route->isWordPressRoute()) {
            $conditionScore = $route->getCondition()->getSpecificity();
            $weightedConditionScore = (int)($conditionScore * $this->getConfig('route_condition_weight'));
            $bonuses['condition_specificity'] = $weightedConditionScore;
            $baseScore += $weightedConditionScore;

            // Parameter bonus
            if ($route->getCondition()->hasParameters()) {
                $parameterBonus = count($route->getCondition()->getParameters()) *
                    $this->getConfig('route_parameter_weight');
                $bonuses['parameters'] = $parameterBonus;
                $baseScore += $parameterBonus;
            }
        } else {
            // Laravel route gets high base score
            $laravelBonus = $this->getConfig('laravel_route_override_threshold');
            $bonuses['laravel_route'] = $laravelBonus;
            $baseScore += $laravelBonus;
        }

        // Context-specific bonuses
        $contextBonuses = $this->calculateRouteContextBonuses($route, $context);
        $bonuses = array_merge($bonuses, $contextBonuses);
        $baseScore += array_sum($contextBonuses);

        return new RouteScore(
            baseScore: $route->getPriority(),
            totalScore: $baseScore,
            bonuses: $bonuses,
            isWordPressRoute: $route->isWordPressRoute(),
            hasParameters: $route->isWordPressRoute() && $route->getCondition()->hasParameters(),
            condition: $route->isWordPressRoute() ? $route->getCondition()->getCondition() : null
        );
    }

    /**
     * Compare Laravel route with special handling
     */
    private function compareLaravelRoute(TemplateHierarchy $template, Route $route, array $context): ComparisonResult
    {
        // Laravel routes generally win unless explicitly configured otherwise
        $threshold = $this->getConfig('laravel_route_override_threshold');
        $templateScore = $this->calculateTemplateScore($template, $context);
        $routeScore = $this->calculateRouteScore($route, $context);

        // Laravel route wins unless template is exceptionally specific
        $templateWins = $templateScore->getTotalScore() > ($routeScore->getTotalScore() + $threshold);

        return new ComparisonResult(
            templateWins: $templateWins,
            templateScore: $templateScore,
            routeScore: $routeScore,
            reasoning: $templateWins
                ? "Template exceeds Laravel route threshold ({$templateScore->getTotalScore()} > {$routeScore->getTotalScore()} + {$threshold})"
                : "Laravel route priority maintained",
            debugInfo: $this->getDebugInfo($template, $route, $context)
        );
    }

    /**
     * Determine winner based on scores and rules
     */
    private function determineWinner(
        TemplateScore $templateScore,
        RouteScore $routeScore,
        TemplateHierarchy $template,
        Route $route
    ): bool {
        $templateTotal = $templateScore->getTotalScore();
        $routeTotal = $routeScore->getTotalScore();

        if (!$templateScore->templateExists()) {
            return false;
        }

        // Clear winner by score
        if ($templateTotal !== $routeTotal) {
            return $templateTotal > $routeTotal;
        }

        // Equal scores - apply tiebreaker rules
        if ($this->getConfig('same_specificity_prefers_template')) {
            return true; // Template wins on equal scores
        }

        // Additional tiebreaker: prefer existing templates
        return $templateScore->templateExists();
    }

    /**
     * Calculate context-specific bonuses for templates
     */
    private function calculateContextBonuses(TemplateHierarchy $template, array $context): array
    {
        $bonuses = [];

        // Admin context bonus
        if (isset($context['is_admin']) && $context['is_admin']) {
            $bonuses['admin_context'] = 50;
        }

        // Custom post type bonus
        if (isset($context['post_type']) && $context['post_type'] !== 'post' && $context['post_type'] !== 'page') {
            $bonuses['custom_post_type'] = 25;
        }

        // Plugin context bonus
        if (isset($context['plugin_active'])) {
            $bonuses['plugin_context'] = 30;
        }

        return $bonuses;
    }

    /**
     * Calculate context-specific bonuses for routes
     */
    private function calculateRouteContextBonuses(Route $route, array $context): array
    {
        $bonuses = [];

        // User-defined priority override
        if (isset($context['route_priority_override'])) {
            $bonuses['priority_override'] = (int) $context['route_priority_override'];
        }

        // Performance bonus for cached routes
        if (isset($context['route_cached']) && $context['route_cached']) {
            $bonuses['performance'] = 10;
        }

        return $bonuses;
    }

    /**
     * Build human-readable reasoning for the decision
     */
    private function buildReasoning(
        TemplateScore $templateScore,
        RouteScore $routeScore,
        TemplateHierarchy $template,
        Route $route
    ): string {
        $templateTotal = $templateScore->getTotalScore();
        $routeTotal = $routeScore->getTotalScore();

        if ($templateTotal > $routeTotal) {
            $difference = $templateTotal - $routeTotal;
            return "Template wins by {$difference} points ({$templateTotal} vs {$routeTotal}). " .
                   $this->getScoreBreakdown($templateScore, 'Template');
        }

        if ($routeTotal > $templateTotal) {
            $difference = $routeTotal - $templateTotal;
            return "Route wins by {$difference} points ({$routeTotal} vs {$templateTotal}). " .
                   $this->getScoreBreakdown($routeScore, 'Route');
        }

        return "Equal scores ({$templateTotal}), template wins by tiebreaker rule.";
    }

    /**
     * Get score breakdown for reasoning
     */
    private function getScoreBreakdown(TemplateScore|RouteScore $score, string $type): string
    {
        $bonuses = $score->getBonuses();
        if (empty($bonuses)) {
            return "{$type} base score: {$score->getBaseScore()}.";
        }

        $bonusDescriptions = [];
        foreach ($bonuses as $bonus => $value) {
            $bonusDescriptions[] = "{$bonus}: +{$value}";
        }

        return "{$type} bonuses: " . implode(', ', $bonusDescriptions) . ".";
    }

    /**
     * Get debug information
     */
    private function getDebugInfo(TemplateHierarchy $template, Route $route, array $context): array
    {
        if (!$this->getConfig('debug_comparison')) {
            return [];
        }

        return [
            'template' => [
                'condition' => $template->getCondition(),
                'templates' => $template->getTemplatesInOrder(),
                'priority' => $template->getPriority(),
                'primary_template' => $template->getPrimaryTemplate(),
            ],
            'route' => [
                'is_wordpress' => $route->isWordPressRoute(),
                'priority' => $route->getPriority(),
                'condition' => $route->isWordPressRoute() ? $route->getCondition()->getCondition() : null,
                'has_parameters' => $route->isWordPressRoute() && $route->getCondition()->hasParameters(),
            ],
            'context' => array_keys($context),
            'config' => $this->config,
        ];
    }

    /**
     * Get configuration value with fallback to default
     */
    private function getConfig(string $key): mixed
    {
        return $this->config[$key] ?? self::DEFAULT_CONFIG[$key];
    }
}

/**
 * Result of template vs route comparison
 */
final class ComparisonResult
{
    public function __construct(
        private readonly bool $templateWins,
        private readonly TemplateScore $templateScore,
        private readonly RouteScore $routeScore,
        private readonly string $reasoning,
        private readonly array $debugInfo = []
    ) {}

    public function templateWins(): bool
    {
        return $this->templateWins;
    }

    public function routeWins(): bool
    {
        return !$this->templateWins;
    }

    public function getTemplateScore(): TemplateScore
    {
        return $this->templateScore;
    }

    public function getRouteScore(): RouteScore
    {
        return $this->routeScore;
    }

    public function getReasoning(): string
    {
        return $this->reasoning;
    }

    public function getDebugInfo(): array
    {
        return $this->debugInfo;
    }

    public function toArray(): array
    {
        return [
            'template_wins' => $this->templateWins,
            'template_score' => $this->templateScore->toArray(),
            'route_score' => $this->routeScore->toArray(),
            'reasoning' => $this->reasoning,
            'debug_info' => $this->debugInfo,
        ];
    }
}

/**
 * Template scoring details
 */
final class TemplateScore
{
    public function __construct(
        private readonly int $baseScore,
        private readonly int $totalScore,
        private readonly array $bonuses,
        private readonly bool $templateExists,
        private readonly ?string $primaryTemplate,
        private readonly int $templateCount
    ) {}

    public function getBaseScore(): int
    {
        return $this->baseScore;
    }

    public function getTotalScore(): int
    {
        return $this->totalScore;
    }

    public function getBonuses(): array
    {
        return $this->bonuses;
    }

    public function templateExists(): bool
    {
        return $this->templateExists;
    }

    public function getPrimaryTemplate(): ?string
    {
        return $this->primaryTemplate;
    }

    public function getTemplateCount(): int
    {
        return $this->templateCount;
    }

    public function toArray(): array
    {
        return [
            'base_score' => $this->baseScore,
            'total_score' => $this->totalScore,
            'bonuses' => $this->bonuses,
            'template_exists' => $this->templateExists,
            'primary_template' => $this->primaryTemplate,
            'template_count' => $this->templateCount,
        ];
    }
}

/**
 * Route scoring details
 */
final class RouteScore
{
    public function __construct(
        private readonly int $baseScore,
        private readonly int $totalScore,
        private readonly array $bonuses,
        private readonly bool $isWordPressRoute,
        private readonly bool $hasParameters,
        private readonly ?string $condition
    ) {}

    public function getBaseScore(): int
    {
        return $this->baseScore;
    }

    public function getTotalScore(): int
    {
        return $this->totalScore;
    }

    public function getBonuses(): array
    {
        return $this->bonuses;
    }

    public function isWordPressRoute(): bool
    {
        return $this->isWordPressRoute;
    }

    public function hasParameters(): bool
    {
        return $this->hasParameters;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    public function toArray(): array
    {
        return [
            'base_score' => $this->baseScore,
            'total_score' => $this->totalScore,
            'bonuses' => $this->bonuses,
            'is_wordpress_route' => $this->isWordPressRoute,
            'has_parameters' => $this->hasParameters,
            'condition' => $this->condition,
        ];
    }
}
