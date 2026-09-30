<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Doctor\Infrastructure\Support\ActiveTheme;
use Pollora\Theme\Application\Services\ThemeAvailability;

/**
 * A theme is installed and its assets are built.
 *
 * Without a Vite manifest and without a dev server, every theme asset URL is
 * empty — get_theme_file_uri() among them — and the page renders unstyled,
 * with no error anywhere.
 */
final readonly class ThemeBuildCheck implements CheckInterface
{
    public function __construct(
        private ThemeAvailability $availability,
        private AssetManager $assets,
    ) {}

    public function id(): string
    {
        return 'theme-build';
    }

    public function label(): string
    {
        return 'Theme and its build';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        if ($this->availability->isMissing()) {
            return CheckResult::error(
                'No theme is installed: the front end cannot render.',
                [],
                'php artisan pollora:make:theme my-theme',
            );
        }

        $directory = ActiveTheme::directory();

        if ($directory === null || ! is_file($directory.'/vite.config.js')) {
            return CheckResult::ok('The active theme has no Vite build.');
        }

        $container = $this->assets->getContainer('theme');

        if (! $container instanceof AssetContainer) {
            return CheckResult::skipped('The active theme registers no asset container.');
        }

        if (is_file($container->getHotFile())) {
            return CheckResult::ok("Vite's dev server serves the assets.", [
                'hot file: '.$container->getHotFile().' (delete it if the dev server is stopped)',
            ]);
        }

        $manifest = public_path(trim($container->getBuildDirectory(), '/').'/'.ltrim($container->getManifestPath(), '/'));

        if (! is_file($manifest)) {
            return CheckResult::error(
                'The theme is not built: its asset URLs are empty and the page renders unstyled.',
                ['no manifest at '.$manifest],
                sprintf('cd %s && npm install && npm run build', ActiveTheme::relativeDirectory()),
            );
        }

        return CheckResult::ok('The theme is built.');
    }
}
