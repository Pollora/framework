<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Models;

use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Infrastructure\Services\AssetFile;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Theme\Domain\Contracts\ContainerInterface;
use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Contracts\WordPressThemeInterface;
use Pollora\Theme\Domain\Support\ThemeConfig;

/**
 * Theme initializer for self-registered themes.
 *
 * This version works with themes that register themselves via functions.php
 * instead of relying on automatic discovery and database queries.
 */
class ThemeInitializer implements ThemeComponent
{
    protected $themeRoot;

    protected $wp_theme;

    protected Action $action;

    protected Filter $filter;

    protected ?ThemeService $themeService = null;

    protected WordPressThemeInterface $wpTheme;

    protected ThemeRegistrarInterface $registrar;

    /**
     * Create a new self-registered theme initializer
     */
    public function __construct(
        protected ContainerInterface $app,
        protected ConfigRepositoryInterface $config
    ) {
        // Get theme root safely - use fallback if ThemeConfig is not initialized yet
        try {
            $this->themeRoot = ThemeConfig::get('path', base_path('themes'));
        } catch (\RuntimeException) {
            // Fallback if ThemeConfig is not initialized yet
            $this->themeRoot = base_path('themes');
        }

        $this->action = $this->app->get(Action::class);
        $this->filter = $this->app->get(Filter::class);
        $this->wpTheme = $this->app->get(WordPressThemeInterface::class);
        $this->registrar = $this->app->get(ThemeRegistrarInterface::class);

        $this->filter->add('pre_option_stylesheet_root', $this->resetThemeRootOption(...));
        $this->filter->add('pre_option_template_root', $this->resetThemeRootOption(...));
    }

    /**
     * Get the ThemeService, resolving it if not already done
     */
    protected function getThemeService(): ThemeService
    {
        if (! $this->themeService instanceof ThemeService) {
            $this->themeService = $this->app->get(ThemeService::class);

            // Fallback to 'theme' binding if ThemeService interface isn't registered yet
            if (! $this->themeService instanceof ThemeService) {
                $this->themeService = $this->app->get('theme');
            }

            if (! $this->themeService instanceof ThemeService) {
                throw new \RuntimeException('Unable to resolve ThemeService. Make sure it is properly registered.');
            }
        }

        return $this->themeService;
    }

    /**
     * Register the theme initializer.
     *
     * Hooks into 'after_setup_theme' at priority 1 to initialize the theme
     * early in the WordPress lifecycle, and overrides the theme URI for
     * proper asset resolution.
     */
    public function register(): void
    {
        $this->action->add('after_setup_theme', $this->initializeTheme(...), 1);
        $this->overrideThemeUri();
    }

    /**
     * Force template and stylesheet root to the configured theme path.
     *
     * Overrides any stale absolute path stored in wp_options,
     * ensuring WordPress always uses the correct theme root.
     */
    protected function resetThemeRootOption(string|bool $path): string
    {
        return $this->themeRoot;
    }

    /**
     * Initialize the theme
     */
    private function initializeTheme(): void
    {
        // Get the active theme from the registrar
        $activeTheme = $this->registrar->getActiveTheme();

        if ($activeTheme instanceof ThemeModuleInterface) {
            // The directory holding the themes, not the theme's own directory:
            // this feeds the stylesheet_root and template_root options, which
            // WordPress joins with the stylesheet name to locate the theme.
            $this->themeRoot = dirname($activeTheme->getPath());

            // Register theme directory with WordPress
            $this->wpTheme->registerThemeDirectory($this->themeRoot);

            // Set up theme metadata
            $this->setThemes($activeTheme->getName());
        } else {
            // No theme registered - this is expected behavior
            // Themes must register themselves via functions.php
            return;
        }

        $this->registerThemeProvider();

        // Theme directories are now registered centrally in ThemeServiceProvider
        // No need to manage $GLOBALS['wp_theme_directories'] here

        // Use the interface to get the theme instance
        $this->wp_theme = $this->wpTheme->getTheme();

        // Use our specialized container interface
        $this->app->bindShared('wp.theme', fn (): object => $this->wp_theme);
    }

    /**
     * Register theme providers
     */
    private function registerThemeProvider(): void
    {
        $providers = (array) ThemeConfig::get('providers', []);

        foreach ($providers as $provider) {
            // Using our specialized container interface
            $this->app->registerProvider($provider);
        }
    }

    /**
     * Set up themes
     */
    public function setThemes(?string $themeName = null): void
    {
        // Theme name is required for self-registered themes
        if (in_array($themeName, [null, '', '0'], true)) {
            throw new \RuntimeException('Theme name is required for self-registered themes.');
        }

        $this->getThemeService()->load($themeName);
    }

    /**
     * Check if the theme is identical to the given theme
     */
    public function isThemeIdentical($childTheme): bool
    {
        // Use the interface instead of direct function call
        return $this->wpTheme->getTemplate() === $childTheme;
    }

    /**
     * Merge configuration from a file
     */
    protected function mergeConfigFrom($path, string $key): void
    {
        $config = $this->app->getConfig($key, []);
        if (! file_exists($path)) {
            return;
        }

        $this->app->setConfig($key, array_merge(require $path, $config));
    }

    /**
     * Resolve theme file URLs through the theme's Vite build.
     *
     * A theme's own directory is not web-served on a Pollora project — only
     * the build output under `/build/theme/{slug}` is — so the only address a
     * theme file can have is the one Vite gave it. This is what makes
     * `get_theme_file_uri()` answer something a browser can fetch.
     */
    public function overrideThemeUri(): void
    {
        $this->filter->add('theme_file_uri', $this->resolveThemeFileUri(...), 10, 2);
    }

    /**
     * Answer `get_theme_file_uri()` with the built asset, or leave it alone.
     *
     * WordPress hands the filter both the URL it built and **the file it was
     * asked for**, relative to the theme. The file is what matters here, and
     * it used to be thrown away: the previous implementation recovered it by
     * subtracting `get_stylesheet_directory_uri()` from the URL by hand, then
     * handed the result to the asset resolver, which prefixes the container's
     * own root — so `resources/assets/app.js` was looked up as
     * `resources/assets/resources/assets/app.js` and never found. Every call
     * returned an empty string, on the front end as much as anywhere else,
     * and the failure was logged and swallowed.
     *
     * Anything the build does not know about is handed back untouched rather
     * than emptied. A caller that gets WordPress's own answer can at least
     * see what went wrong; a caller that gets `''` cannot.
     *
     * @param  mixed  $url  The URL WordPress built
     * @param  mixed  $file  The file asked for, relative to the theme root
     * @return mixed The built asset URL, or WordPress's own answer
     */
    public function resolveThemeFileUri(mixed $url, mixed $file = ''): mixed
    {
        if (! is_string($file) || trim($file) === '') {
            return $url;
        }

        return $this->builtAssetUrl($file) ?? $url;
    }

    /**
     * The URL Vite gave a file, if it built one.
     */
    protected function builtAssetUrl(string $file): ?string
    {
        foreach ($this->manifestCandidates($file, $this->assetRoot()) as $candidate) {
            $url = (string) (new AssetFile($candidate))->from('theme');

            if ($url !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * The spellings under which a theme-relative file may sit in the manifest.
     *
     * The asset container prefixes every lookup with its own root — normally
     * `resources/assets/` — while `get_theme_file_uri()` is given a path from
     * the theme's root. The two meet in one of two ways, and both are tried:
     * the caller already spelled the container root, in which case it has to
     * come off before the container puts it back; or the caller spelled the
     * path the container expects, in which case it passes straight through.
     *
     * @return list<string>
     */
    public function manifestCandidates(string $file, string $assetRoot): array
    {
        $file = ltrim(trim($file), '/');
        $assetRoot = trim($assetRoot, '/');

        $candidates = [];

        if ($assetRoot !== '' && str_starts_with($file, $assetRoot.'/')) {
            $candidates[] = substr($file, strlen($assetRoot) + 1);
        }

        $candidates[] = $file;

        return array_values(array_unique(array_filter($candidates, fn (string $c): bool => $c !== '')));
    }

    /**
     * The container root every manifest lookup is prefixed with.
     */
    protected function assetRoot(): string
    {
        try {
            $container = $this->app->get(AssetManager::class)->getContainer('theme');

            return $container === null ? '' : $container->getBasePath();
        } catch (\Throwable) {
            return '';
        }
    }
}
