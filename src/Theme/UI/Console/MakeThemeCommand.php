<?php

declare(strict_types=1);

namespace Pollora\Theme\UI\Console;

use Composer\InstalledVersions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Pollora\Console\Concerns\PromptsForMissingOption;
use Pollora\Console\Contracts\PromptsForMissingOption as PromptsForMissingOptionContract;
use Pollora\Modules\Infrastructure\Services\ModuleScaffolderService;
use Pollora\Support\NpmRunner;
use Pollora\Theme\Domain\Models\ThemeMetadata;
use Pollora\Translation\Domain\Contracts\TranslationCompilerInterface;
use Pollora\Translation\Infrastructure\Services\GettextMoCompiler;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Artisan command to scaffold a new theme directory structure.
 *
 * This command creates a new theme by downloading from a GitHub repository,
 * performing string replacements, running npm install/build, and optionally
 * setting the theme as the active WordPress theme.
 */
#[Description('Generate theme structure by downloading from GitHub repository')]
#[Signature('pollora:make:theme {name} {--theme-author= : Theme author name} {--theme-author-uri= : Theme author URI} {--theme-uri= : Theme URI} {--theme-description= : Theme description} {--theme-version= : Theme version} {--repository= : GitHub repository to download (owner/repo format)} {--repo-version= : Specific version/tag to download} {--force : Force create theme with same name}')]
class MakeThemeCommand extends BaseThemeCommand implements PromptsForMissingInput, PromptsForMissingOptionContract
{
    use PromptsForMissingOption;

    /**
     * The ThemeMetadata instance representing the theme being created.
     */
    protected ThemeMetadata $theme;

    /**
     * Container folder mapping (assets, lang, layouts, etc).
     *
     * @var array<string, string>
     */
    protected array $containerFolder;

    /**
     * The module scaffolder service.
     */
    protected ModuleScaffolderService $scaffolder;

    /**
     * Handle the command execution.
     */
    public function handle(ModuleScaffolderService $scaffolder): int
    {
        $this->scaffolder = $scaffolder;
        $this->theme = $this->makeTheme($this->argument('name'));

        if (! $this->validateThemeName() || ! $this->canGenerateTheme()) {
            return self::FAILURE;
        }

        $this->setupContainerFolders();

        $repository = $this->promptForRepository();
        $repo = in_array($repository, [null, '', '0'], true) ? self::TEMPLATES['default'] : $repository;

        $success = $this->scaffolder->downloadAndScaffold(
            repository: $repo,
            basePath: $this->getThemesPath(),
            targetPath: $this->theme->getBasePath(),
            replacements: $this->getReplacements(),
            version: $this->option('repo-version'),
            output: $this->getOutput(),
            removeDirs: ['bin'],
        );

        if (! $success) {
            $this->scaffolder->copyDirectory(
                $this->getTemplatePath('common'),
                $this->theme->getBasePath()
            );
        }

        $this->info(sprintf('Theme "%s" created successfully.', $this->theme->getName()));

        $this->compileTranslations();
        $this->installRequirementsIfNeeded();
        $this->runNpmIfNeeded();
        $this->promptAndSetActiveTheme();

        return self::SUCCESS;
    }

    /**
     * Validate the theme name.
     */
    protected function validateThemeName(): bool
    {
        $message = $this->validateValue($this->argument('name'));
        if (! in_array($message, [null, '', '0'], true)) {
            $this->error($message);

            return false;
        }

        return true;
    }

    /**
     * Check if the theme can be generated.
     */
    protected function canGenerateTheme(): bool
    {
        if (! $this->directoryExists()) {
            return true;
        }

        $name = $this->theme->getName();
        $this->error(sprintf('Theme "%s" already exists.', $name));

        if ($this->option('force')) {
            return true;
        }

        return $this->confirm(sprintf('Are you sure you want to override "%s" theme folder?', $name));
    }

    /**
     * Setup container folders.
     */
    protected function setupContainerFolders(): self
    {
        $dirMapping = $this->config->get('theme.structure', []);
        $this->containerFolder = [
            'assets' => $dirMapping['assets'] ?? 'resources/assets',
            'lang' => $dirMapping['lang'] ?? 'lang',
            'layout' => $dirMapping['layouts'] ?? 'resources/views/layouts',
            'partial' => $dirMapping['partials'] ?? 'resources/views/partials',
            'view' => $dirMapping['views'] ?? 'resources/views',
        ];

        return $this;
    }

    /**
     * Compile .po translation files into .mo binary format.
     *
     * Scans the theme's languages directory and compiles every .po file
     * found using a pure-PHP gettext compiler (no system tools required).
     */
    protected function compileTranslations(): void
    {
        $langDir = $this->theme->getBasePath().'/languages';

        if (! is_dir($langDir) || glob($langDir.'/*.po') === []) {
            return;
        }

        /** @var TranslationCompilerInterface $compiler */
        $compiler = $this->laravel->bound(TranslationCompilerInterface::class)
            ? $this->laravel->make(TranslationCompilerInterface::class)
            : new GettextMoCompiler;

        $compiled = $compiler->compileDirectory($langDir);

        if ($compiled > 0) {
            $this->info(sprintf('Compiled %d translation file(s).', $compiled));
        }
    }

    /**
     * Check for theme requirements and offer to install them.
     */
    protected function installRequirementsIfNeeded(): void
    {
        $requirementsFile = $this->theme->getBasePath().'/requirements.json';

        if (! file_exists($requirementsFile)) {
            return;
        }

        $requirements = json_decode(file_get_contents($requirementsFile), true);
        $composerPackages = $requirements['composer'] ?? [];

        if (empty($composerPackages)) {
            return;
        }

        $this->newLine();
        $this->info('This theme requires the following Composer packages:');

        $options = [];
        foreach ($composerPackages as $package => $description) {
            $options[$package] = sprintf('%s — %s', $package, $description);
        }

        $selected = multiselect(
            label: 'Which packages would you like to install?',
            options: $options,
            default: array_keys($options),
            hint: 'Press space to toggle, enter to confirm. Leave empty to skip.',
        );

        if ($selected === []) {
            $this->info('Skipping package installation.');

            return;
        }

        $this->info('Installing Composer packages...');

        $command = ['composer', 'require', '-W', ...$selected];
        $process = new Process($command, base_path());
        $process->setTimeout(300);
        $process->run(function ($type, string|iterable $buffer): void {
            $this->getOutput()->write($buffer);
        });

        if ($process->isSuccessful()) {
            $this->info('Composer packages installed successfully.');
            $this->activateWordPressPlugins($selected);
        } else {
            $this->error('Failed to install some Composer packages. You can install them manually:');
            $this->line('  composer require '.implode(' ', $selected));
        }
    }

    /**
     * Detect WordPress plugins among installed packages and offer to activate them.
     *
     * Uses `composer show` to inspect each package type. Packages of type
     * `wordpress-plugin` are collected and, if `activate_plugin()` is available,
     * the user is prompted to activate them in WordPress.
     *
     * @param  array<int, string>  $packages  Composer package names that were just installed.
     */
    protected function activateWordPressPlugins(array $packages): void
    {
        if (! function_exists('activate_plugin')) {
            return;
        }

        $plugins = $this->resolveWordPressPlugins($packages);

        if ($plugins === []) {
            return;
        }

        $shouldActivate = confirm(
            label: count($plugins) === 1
                ? sprintf('Activate the WordPress plugin "%s"?', array_values($plugins)[0])
                : sprintf('Activate %d WordPress plugins?', count($plugins)),
            default: true,
        );

        if (! $shouldActivate) {
            return;
        }

        foreach ($plugins as $slug) {
            $pluginFile = $this->findPluginEntryFile($slug);

            if ($pluginFile === null) {
                $this->warn(sprintf('Could not find entry file for plugin "%s".', $slug));

                continue;
            }

            $result = activate_plugin($pluginFile);

            if (is_wp_error($result)) {
                $this->error(sprintf('Failed to activate "%s": %s', $slug, $result->get_error_message()));
            } else {
                $this->info(sprintf('Plugin "%s" activated.', $slug));
            }
        }
    }

    /**
     * Filter a list of Composer packages to only those of type `wordpress-plugin`.
     *
     * Uses Composer's runtime API ({@see InstalledVersions}) to check package
     * types without spawning a subprocess. Extracts the plugin slug from the
     * package name (e.g. "woocommerce" from "wpackagist-plugin/woocommerce").
     *
     * @param  array<int, string>  $packages  Composer package names.
     * @return array<string, string> Map of package name => plugin directory slug.
     */
    private function resolveWordPressPlugins(array $packages): array
    {
        $installedPlugins = InstalledVersions::getInstalledPackagesByType('wordpress-plugin');
        $plugins = [];

        foreach ($packages as $package) {
            if (in_array($package, $installedPlugins, true)) {
                $slug = substr($package, (int) strpos($package, '/') + 1);
                $plugins[$package] = $slug;
            }
        }

        return $plugins;
    }

    /**
     * Find the main PHP entry file for a WordPress plugin.
     *
     * Scans the plugin directory for the file containing the `Plugin Name:`
     * header, which WordPress uses to identify the plugin entry point.
     *
     * @param  string  $slug  The plugin directory name (e.g. "woocommerce").
     * @return string|null The plugin-relative path (e.g. "woocommerce/woocommerce.php"), or null if not found.
     */
    private function findPluginEntryFile(string $slug): ?string
    {
        $pluginDir = defined('WP_PLUGIN_DIR')
            ? WP_PLUGIN_DIR.'/'.$slug
            : base_path('public/content/plugins/'.$slug);

        if (! is_dir($pluginDir)) {
            return null;
        }

        // Check the conventional entry file first (slug.php)
        $conventionalFile = $pluginDir.'/'.$slug.'.php';
        if (file_exists($conventionalFile) && $this->isPluginFile($conventionalFile)) {
            return $slug.'/'.$slug.'.php';
        }

        // Fallback: scan top-level PHP files for the Plugin Name header
        foreach (glob($pluginDir.'/*.php') as $file) {
            if ($this->isPluginFile($file)) {
                return $slug.'/'.basename($file);
            }
        }

        return null;
    }

    /**
     * Check whether a PHP file contains the WordPress `Plugin Name:` header.
     *
     * Only reads the first 8 KB of the file (the maximum WordPress inspects)
     * to avoid loading large files into memory.
     *
     * @param  string  $filePath  Absolute path to the PHP file.
     */
    private function isPluginFile(string $filePath): bool
    {
        $content = file_get_contents($filePath, false, null, 0, 8192);

        return $content !== false && str_contains($content, 'Plugin Name:');
    }

    /**
     * Run npm install and build in the theme directory.
     */
    protected function runNpmIfNeeded(): void
    {
        if (is_dir($this->theme->getBasePath())) {
            $this->info('Running npm install and npm run build in '.$this->theme->getBasePath().' ...');
            try {
                (new NpmRunner($this->theme->getBasePath()))
                    ->install()
                    ->build();
                $this->info('npm install and build completed.');
            } catch (\Throwable $e) {
                $this->error('npm install or build failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Prompt to set the theme as active and do so if confirmed.
     */
    protected function promptAndSetActiveTheme(): void
    {
        $shouldSetActive = select(
            label: 'Do you want to set "'.$this->theme->getName().'" as the active WordPress theme?',
            options: ['yes' => 'Yes', 'no' => 'No'],
            default: 'yes',
            hint: 'Selecting "Yes" will set this theme as the active one in WordPress.'
        );

        if ($shouldSetActive === 'yes') {
            if (function_exists('update_option')) {
                update_option('stylesheet', $this->theme->getName());
                update_option('template', $this->theme->getName());
                $this->info('Theme "'.$this->theme->getName().'" is now set as the active WordPress theme.');
            } else {
                $this->warn('Unable to set the theme as active: WordPress functions are not available in this context.');
            }
        }
    }

    /**
     * Get placeholder replacements for scaffolding.
     */
    protected function getReplacements(): array
    {
        return [
            '%theme_name%' => $this->theme->getName(),
            '%theme_camel%' => $this->theme->getThemeCamelCase(),
            '%theme_author%' => $this->option('theme-author'),
            '%theme_author_uri%' => $this->option('theme-author-uri'),
            '%theme_uri%' => $this->option('theme-uri'),
            '%theme_description%' => $this->option('theme-description'),
            '%theme_version%' => $this->option('theme-version'),
            '%theme_namespace%' => $this->theme->getThemeNamespace(),
        ];
    }

    /**
     * Prompt for missing options using the returned questions.
     */
    protected function promptForMissingOptionsUsing(): array
    {
        return [
            'theme-author' => [
                'label' => 'What is the author of the new theme?',
                'default' => 'Pollora',
                'validation' => 'required',
            ],
            'theme-author-uri' => [
                'label' => 'What is the URL of the theme author?',
                'default' => 'https://pollora.dev',
                'validation' => 'required|url',
            ],
            'theme-uri' => [
                'label' => 'What is the URL of the theme?',
                'default' => 'https://pollora.dev',
                'validation' => 'required|url',
            ],
            'theme-description' => [
                'label' => 'What is the description of the new theme?',
                'default' => 'A new theme using Pollora Framework',
                'validation' => 'required',
            ],
            'theme-version' => [
                'label' => 'What is the version of the theme?',
                'default' => '1.0',
                'validation' => 'required',
            ],
        ];
    }

    /**
     * Prompt for missing arguments.
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => fn (): string => text(
                label: 'What is a name of the new theme?',
                default: 'default',
                validate: fn (string $value): ?string => $this->validateValue($value)
            ),
        ];
    }

    /**
     * Built-in theme templates mapped to their GitHub repositories.
     */
    protected const TEMPLATES = [
        'default' => 'pollora/theme-default',
        'ecommerce' => 'pollora/theme-apiary',
    ];

    /**
     * Prompt for repository if not provided.
     */
    protected function promptForRepository(): ?string
    {
        if ($this->option('repository')) {
            return $this->option('repository');
        }

        $choice = select(
            label: 'Which theme template would you like to use?',
            options: [
                'default' => 'Default — Basic starter theme',
                'ecommerce' => 'E-commerce — WooCommerce theme (Tailwind CSS, Alpine.js)',
                'repository' => 'Custom — Download from a GitHub repository',
            ],
            default: 'default'
        );

        if ($choice === 'repository') {
            return text(
                label: 'Enter the GitHub repository (owner/repo format):',
                placeholder: 'pollora/theme-default',
                validate: function ($value): ?string {
                    if (empty($value)) {
                        return 'Repository is required';
                    }

                    if (! str_contains($value, '/')) {
                        return 'Repository must be in owner/repo format';
                    }

                    return null;
                }
            );
        }

        return self::TEMPLATES[$choice] ?? null;
    }

    /**
     * Validate value.
     */
    protected function validateValue(string $value): ?string
    {
        return match (true) {
            $value === '' || $value === '0' => 'Name is required.',
            preg_match('/[^a-zA-Z0-9\-_\s]/', $value) !== 0 && preg_match('/[^a-zA-Z0-9\-_\s]/', $value) !== false => 'Name must be alphanumeric, dash, space or underscore.',
            $this->files->isDirectory($this->makeTheme($value)->getBasePath()) => sprintf('Theme "%s" already exists.', $value),
            default => null,
        };
    }
}
