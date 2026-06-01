<?php

declare(strict_types=1);

namespace Pollora\Theme\UI\Console;

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
class MakeThemeCommand extends BaseThemeCommand implements PromptsForMissingInput, PromptsForMissingOptionContract
{
    use PromptsForMissingOption;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pollora:make-theme {name} {--theme-author= : Theme author name} {--theme-author-uri= : Theme author URI} {--theme-uri= : Theme URI} {--theme-description= : Theme description} {--theme-version= : Theme version} {--repository= : GitHub repository to download (owner/repo format)} {--repo-version= : Specific version/tag to download} {--force : Force create theme with same name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate theme structure by downloading from GitHub repository';

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
            $options[$package] = "{$package} — {$description}";
        }

        $selected = multiselect(
            label: 'Which packages would you like to install?',
            options: $options,
            default: array_keys($options),
            hint: 'Press space to toggle, enter to confirm. Leave empty to skip.',
        );

        if (empty($selected)) {
            $this->info('Skipping package installation.');

            return;
        }

        $this->info('Installing Composer packages...');

        $command = ['composer', 'require', '-W', ...$selected];
        $process = new Process($command, base_path());
        $process->setTimeout(300);
        $process->run(function ($type, $buffer): void {
            $this->getOutput()->write($buffer);
        });

        if ($process->isSuccessful()) {
            $this->info('Composer packages installed successfully.');
        } else {
            $this->error('Failed to install some Composer packages. You can install them manually:');
            $this->line('  composer require '.implode(' ', $selected));
        }
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
