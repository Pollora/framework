<?php

declare(strict_types=1);

namespace Pollora\Plugin\UI\Console;

use Illuminate\Config\Repository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Pollora\Console\Concerns\PromptsForMissingOption;
use Pollora\Console\Contracts\PromptsForMissingOption as PromptsForMissingOptionContract;
use Pollora\Modules\Infrastructure\Services\ModuleScaffolderService;
use Pollora\Plugin\Domain\Models\PluginMetadata;
use Pollora\Support\NpmRunner;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Artisan command to scaffold a new plugin directory structure.
 *
 * This command creates a new plugin by downloading from a GitHub repository,
 * performing string replacements, running npm install/build, and setting up
 * the plugin structure following WordPress and Laravel conventions.
 */
#[Description('Generate plugin structure by downloading from GitHub repository')]
#[Signature('pollora:make:plugin {name}
    {--plugin-author= : Plugin author name}
    {--plugin-author-uri= : Plugin author URI}
    {--plugin-uri= : Plugin URI}
    {--plugin-description= : Plugin description}
    {--plugin-version= : Plugin version}
    {--repository= : GitHub repository to download (owner/repo format)}
    {--repo-version= : Specific version/tag to download}
    {--asset= : Include asset files (JS/CSS) with ViteJS compilation (true/false)}
    {--activate-plugin= : Activate the plugin after creation (yes/no)}
    {--force : Force create plugin with same name}')]
class MakePluginCommand extends Command implements PromptsForMissingInput, PromptsForMissingOptionContract
{
    use PromptsForMissingOption;

    /**
     * List of files and directories to exclude when --asset is false.
     *
     * @var array<int, string>
     */
    protected $assetFilesToExclude = [
        'vite.config.js',
        'tailwind.config.js',
        'postcss.config.mjs',
        'package.json',
        'app/Providers/AssetServiceProvider.stub',
        'resources/assets/',
    ];

    /**
     * The PluginMetadata instance representing the plugin being created.
     */
    protected PluginMetadata $plugin;

    /**
     * Create a new command instance.
     */
    public function __construct(
        protected Repository $config,
        protected Filesystem $files,
        protected ModuleScaffolderService $scaffolder
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int Command exit code
     */
    public function handle(): int
    {
        $this->plugin = $this->makePlugin($this->argument('name'));

        if (! $this->validatePluginName() || ! $this->canGeneratePlugin()) {
            return self::FAILURE;
        }

        $repository = $this->promptForRepository();
        $repo = in_array($repository, [null, '', '0'], true) ? 'pollora/plugin-default' : $repository;

        $success = $this->scaffolder->downloadAndScaffold(
            repository: $repo,
            basePath: $this->getPluginsPath(),
            targetPath: $this->plugin->getBasePath(),
            replacements: $this->getReplacements(),
            version: $this->option('repo-version'),
            output: $this->getOutput(),
            fileFilter: fn (object $item): bool => ! $this->shouldExcludeAssetFile($item),
        );

        if (! $success) {
            $this->scaffolder->copyDirectory(
                $this->getTemplatePath('common'),
                $this->plugin->getBasePath()
            );
        }

        $this->info(sprintf('Plugin %s created successfully at %s', $this->plugin->getName(), $this->plugin->getBasePath()));

        $this->runNpmIfNeeded();
        $this->promptAndActivatePlugin();

        return self::SUCCESS;
    }

    /**
     * Validate the plugin name.
     */
    protected function validatePluginName(): bool
    {
        $message = $this->validateValue($this->argument('name'));
        if (! in_array($message, [null, '', '0'], true)) {
            $this->error($message);

            return false;
        }

        return true;
    }

    /**
     * Check if the plugin can be generated.
     */
    protected function canGeneratePlugin(): bool
    {
        if (! $this->files->isDirectory($this->plugin->getBasePath())) {
            return true;
        }

        $name = $this->plugin->getName();
        $this->error(sprintf('Plugin "%s" already exists.', $name));

        if ($this->option('force')) {
            return $this->confirm(sprintf('Are you sure you want to override "%s" plugin folder?', $name));
        }

        return false;
    }

    /**
     * Run npm install and build if assets are enabled.
     */
    protected function runNpmIfNeeded(): void
    {
        if ($this->shouldIncludeAssets() && is_dir($this->plugin->getBasePath())) {
            $this->info('Running npm install and npm run build in '.$this->plugin->getBasePath().' ...');
            try {
                (new NpmRunner($this->plugin->getBasePath()))
                    ->install()
                    ->build();
                $this->info('npm install and build completed.');
            } catch (\Throwable $e) {
                $this->error('npm install or build failed: '.$e->getMessage());
            }
        } elseif (! $this->shouldIncludeAssets()) {
            $this->info('Assets disabled, skipping npm install/build.');
        }
    }

    /**
     * Prompt to activate the plugin and do so if confirmed.
     */
    protected function promptAndActivatePlugin(): void
    {
        $shouldActivate = $this->option('activate-plugin');
        if ($shouldActivate === null) {
            $shouldActivate = select(
                label: 'Do you want to activate "'.$this->plugin->getName().'" plugin?',
                options: ['yes' => 'Yes', 'no' => 'No'],
                default: 'yes',
                hint: 'Selecting "Yes" will activate this plugin in WordPress.'
            );
        } else {
            $shouldActivate = $this->shouldActivate() ? 'yes' : 'no';
        }

        if ($shouldActivate === 'yes') {
            if (function_exists('activate_plugin')) {
                $result = activate_plugin($this->plugin->getBasename());
                if (is_wp_error($result)) {
                    $this->warn('Unable to activate the plugin: '.$result->get_error_message());
                } else {
                    $this->info('Plugin "'.$this->plugin->getName().'" has been activated.');
                }
            } else {
                $this->warn('Unable to activate the plugin: WordPress functions are not available in this context.');
            }
        }
    }

    /**
     * Check if asset file should be excluded based on --asset option.
     */
    protected function shouldExcludeAssetFile(object $item): bool
    {
        if ($this->shouldIncludeAssets()) {
            return false;
        }

        $relativePath = $item->getRelativePathname();

        foreach ($this->assetFilesToExclude as $excludePattern) {
            if (str_ends_with($excludePattern, '/')) {
                if (str_starts_with((string) $relativePath, $excludePattern)) {
                    return true;
                }
            } elseif ($relativePath === $excludePattern || str_ends_with((string) $relativePath, '/'.$excludePattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if assets should be included based on --asset option.
     */
    protected function shouldIncludeAssets(): bool
    {
        $assetOption = $this->option('asset');

        if ($assetOption === null) {
            return false;
        }

        if (is_string($assetOption)) {
            return in_array(strtolower($assetOption), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $assetOption;
    }

    protected function shouldActivate(): bool
    {
        $activateOption = $this->option('activate-plugin');

        if ($activateOption === null) {
            return true;
        }

        if (is_string($activateOption)) {
            return in_array(strtolower($activateOption), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $activateOption;
    }

    /**
     * Get placeholder replacements for scaffolding.
     */
    protected function getReplacements(): array
    {
        $pluginName = $this->plugin->getName();
        $functionName = $this->sanitizeForPhpFunction($pluginName);

        return [
            '%plugin_name%' => $pluginName,
            '%plugin_function_name%' => $functionName,
            '%PLUGIN_FUNCTION_NAME%' => strtoupper($functionName),
            '%PLUGIN_NAME%' => strtoupper($functionName),
            '%plugin_author%' => $this->option('plugin-author'),
            '%plugin_author_uri%' => $this->option('plugin-author-uri'),
            '%plugin_uri%' => $this->option('plugin-uri'),
            '%plugin_description%' => $this->option('plugin-description'),
            '%plugin_version%' => $this->option('plugin-version'),
            '%plugin_namespace%' => $this->plugin->getPluginNamespace(),
            '%plugin_slug%' => $this->plugin->getSlug(),
            '%plugin_basename%' => $this->plugin->getBasename(),
        ];
    }

    /**
     * Sanitize plugin name for use in PHP function names.
     */
    protected function sanitizeForPhpFunction(string $pluginName): string
    {
        $sanitized = strtolower($pluginName);
        $sanitized = preg_replace('/[^a-z0-9_]/', '_', $sanitized);
        $sanitized = preg_replace('/_+/', '_', (string) $sanitized);
        $sanitized = trim((string) $sanitized, '_');

        if (preg_match('/^\d/', $sanitized)) {
            return '_'.$sanitized;
        }

        return $sanitized;
    }

    /**
     * Prompt for missing options using the returned questions.
     */
    protected function promptForMissingOptionsUsing(): array
    {
        return [
            'plugin-author' => [
                'label' => 'What is the author of the new plugin?',
                'default' => 'Pollora',
                'validation' => 'required',
            ],
            'plugin-author-uri' => [
                'label' => 'What is the URL of the plugin author?',
                'default' => 'https://pollora.dev',
                'validation' => 'required|url',
            ],
            'plugin-uri' => [
                'label' => 'What is the URL of the plugin?',
                'default' => 'https://pollora.dev',
                'validation' => 'required|url',
            ],
            'plugin-description' => [
                'label' => 'What is the description of the new plugin?',
                'default' => 'A new plugin using Pollora Framework',
                'validation' => 'required',
            ],
            'plugin-version' => [
                'label' => 'What is the version of the plugin?',
                'default' => '1.0.0',
                'validation' => 'required',
            ],
            'activate-plugin' => [
                'label' => 'Do you want to activate the plugin after creation?',
                'type' => 'select',
                'options' => ['yes' => 'Yes', 'no' => 'No'],
                'default' => 'yes',
            ],
            'asset' => [
                'label' => 'Do you want to include asset files (JS/CSS) with ViteJS compilation?',
                'type' => 'select',
                'options' => ['false' => 'No (minimal plugin)', 'true' => 'Yes (with ViteJS, Tailwind CSS, etc.)'],
                'default' => 'false',
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
                label: 'What is the name of the new plugin?',
                default: 'default-plugin',
                validate: fn (string $value): ?string => $this->validateValue($value)
            ),
        ];
    }

    /**
     * Prompt for repository if not provided.
     */
    protected function promptForRepository(): ?string
    {
        if ($this->option('repository')) {
            return $this->option('repository');
        }

        $useRepository = select(
            label: 'How would you like to create the plugin?',
            options: [
                'repository' => 'Download from GitHub repository',
                'default' => 'Use default plugin template',
            ],
            default: 'default'
        );

        if ($useRepository === 'repository') {
            return text(
                label: 'Enter the GitHub repository (owner/repo format):',
                placeholder: 'pollora/plugin-default',
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

        return null;
    }

    /**
     * Validate value.
     */
    protected function validateValue(string $value): ?string
    {
        return match (true) {
            $value === '' || $value === '0' => 'Name is required.',
            preg_match('/[^a-zA-Z0-9\-_\s]/', $value) !== 0 && preg_match('/[^a-zA-Z0-9\-_\s]/', $value) !== false => 'Name must be alphanumeric, dash, space or underscore.',
            $this->files->isDirectory($this->makePlugin($value)->getBasePath()) => sprintf('Plugin "%s" already exists.', $value),
            default => null,
        };
    }

    /**
     * Get template path.
     */
    protected function getTemplatePath(string $templateName): string
    {
        return realpath(__DIR__.'/../../stubs/'.$templateName);
    }

    /**
     * Make plugin metadata instance.
     */
    protected function makePlugin(string $name): PluginMetadata
    {
        return new PluginMetadata($name, $this->getPluginsPath());
    }

    /**
     * Get plugins path.
     */
    protected function getPluginsPath(): string
    {
        if (defined('WP_PLUGIN_DIR')) {
            return WP_PLUGIN_DIR;
        }

        return $this->config->get('plugin.path', public_path('content/plugins'));
    }
}
