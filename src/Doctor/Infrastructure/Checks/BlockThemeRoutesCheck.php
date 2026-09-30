<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Illuminate\Routing\Router;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Route\Infrastructure\Models\Route;

/**
 * No Route::wp() route answers in place of a block theme's templates.
 *
 * A route answers before the template hierarchy: with a block theme, a
 * Route::wp('single', …) means the Site Editor's single template is never
 * rendered. The 13.4 skeleton shipped such routes in routes/web.php.
 */
final readonly class BlockThemeRoutesCheck implements CheckInterface
{
    public function __construct(private Router $router) {}

    public function id(): string
    {
        return 'block-theme-routes';
    }

    public function label(): string
    {
        return 'Routes over block templates';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        if (! function_exists('wp_is_block_theme') || ! wp_is_block_theme()) {
            return CheckResult::skipped('The active theme is not a block theme.');
        }

        $routes = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if ($route instanceof Route && $route->isWordPressRoute() && $route->hasCondition()) {
                $routes[] = sprintf('Route::wp(\'%s\') → %s', $route->getCondition(), $route->getActionName());
            }
        }

        if ($routes !== []) {
            return CheckResult::warning(
                sprintf('%d Route::wp() route(s) answer before the block templates, which then never render.', count($routes)),
                $routes,
                'Remove them from routes/web.php, unless they are meant to replace a block template',
            );
        }

        return CheckResult::ok('No route answers in place of the block templates.');
    }
}
