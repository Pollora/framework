<?php

/**
 * Class MakeThemeCommand
 *
 * Artisan command to scaffold a new theme directory structure by downloading from GitHub repository,
 * perform string replacements, run npm install/build, and optionally set the theme as the active WordPress theme.
 */
declare(strict_types=1);

namespace Pollora\Theme\UI\Console;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Pollora\Console\Concerns\PromptsForMissingOption;
use Pollora\Console\Contracts\PromptsForMissingOption as PromptsForMissingOptionContract;
use Pollora\Modules\Infrastructure\Services\ModuleDownloader;
use Pollora\Support\NpmRunner;
use Pollora\Theme\Domain\Models\ThemeMetadata;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

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
     * List of file extensions considered as text for replacements.
     *
     * @var array<int, string>
     */
    protected $textExtensions = ['php', 'js', 'css', 'html', 'htm', 'xml', 'txt', 'md', 'json', 'yaml', 'yml', 'svg', 'twig', 'blade.php', 'stub'];

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
     * Constructor.
     */
    public function __construct(Repository $config, Filesystem $files)
    {
        parent::__construct($config, $files);
    }

    /**
     * Handle the command execution.
     */
    public function handle(): int
    {
        $this->theme = $this->makeTheme($this->argument('name'));

        if (! $this->validateThemeName() || ! $this->canGenerateTheme()) {
            return self::FAILURE;
        }

        $this->setupContainerFolders();

        $repository = $this->promptForRepository();

        if ($repository !== null && $repository !== '' && $repository !== '0') {
            $this->downloadFromRepository($repository);
        } else {
            // Use default repository instead of local stubs
            $this->downloadFromRepository('pollora/theme-default');
        }

        $this->info("Theme \"{$this->theme->getName()}\" created successfully.");

        // Run npm install and npm run build in the frontend directory of the new theme
        if (is_dir($this->theme->getBasePath())) {
            $this->info('Running npm install and npm run build in '.$this->theme->getBasePath().' ...');
            try {
                (new NpmRunner($this->theme->getBasePath()))
                    ->install()
                    ->build();
                $this->info('npm install and build completed.');
            } catch (\Throwable $e) {
                $this->error('npm install or build failed: '.$e->getMessage());
                // Continue script even if npm fails
            }
        } else {
            $this->info('No frontend directory found at '.$this->theme->getBasePath().', skipping npm install/build.');
        }

        // Prompt to set this theme as the active WordPress theme
        $shouldSetActive = select(
            label: 'Do you want to set "'.$this->theme->getName().'" as the active WordPress theme?',
            options: [
                'yes' => 'Yes',
                'no' => 'No',
            ],
            default: 'yes',
            hint: 'Selecting "Yes" will set this theme as the active one in WordPress.'
        );
        if ($shouldSetActive === 'yes') {
            // Set the theme as active in WordPress (update the stylesheet and template options)
            if (function_exists('update_option')) {
                update_option('stylesheet', $this->theme->getName());
                update_option('template', $this->theme->getName());
                $this->info('Theme "'.$this->theme->getName().'" is now set as the active WordPress theme.');
            } else {
                $this->warn('Unable to set the theme as active: WordPress functions are not available in this context.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Validate the theme name.
     */
    protected function validateThemeName(): bool
    {
        $message = $this->validateValue($this->argument('name'));
        if ($message !== null && $message !== '' && $message !== '0') {
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

        $this->error("Theme \"{$name}\" already exists.");
        if ($this->option('force')) {
            return true;
        }

        return $this->confirm("Are you sure you want to override \"{$name}\" theme folder?");
    }

    /**
     * Setup container folders.
     *
     * @return $this
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
     * Generate theme structure from stubs.
     */
    protected function generateThemeStructure(): void
    {
        $this->copyDirectory($this->getTemplatePath('common'), $this->theme->getBasePath());
    }

    /**
     * Download theme from GitHub repository.
     */
    protected function downloadFromRepository(string $repository): void
    {
        $version = $this->option('repo-version');

        try {
            $downloader = new ModuleDownloader($repository);

            if ($version) {
                $downloader->setVersion($version);
            }

            $this->info("Downloading theme from {$repository}".($version ? " (version: {$version})" : '').'...');

            $extractedPath = $downloader->downloadAndExtract($this->getThemesPath());

            // Move contents from extracted folder to theme folder
            $this->moveExtractedTheme($extractedPath);

            $this->info('Theme downloaded and extracted successfully.');

        } catch (\Exception $e) {
            $this->error("Failed to download theme: {$e->getMessage()}");

            // Fallback to generating structure if download fails
            $this->warn('Falling back to generating default theme structure...');
            $this->generateThemeStructure();
        }
    }

    /**
     * Move extracted theme contents to the proper theme directory.
     */
    protected function moveExtractedTheme(string $extractedPath): void
    {
        $targetPath = $this->theme->getBasePath();

        // Ensure target directory exists
        $this->ensureDirectoryExists($targetPath);

        // Move all contents from extracted path to target path with replacements
        $this->copyDirectoryWithReplacements($extractedPath, $targetPath);

        // Clean up the extracted directory
        $this->removeDirectory(dirname($extractedPath));
    }

    /**
     * Remove directory recursively.
     */
    protected function removeDirectory(string $path): void
    {
        if (is_dir($path)) {
            File::deleteDirectory($path);
        }
    }


    /**
     * Copy directory.
     *
     * @param  string  $source
     */
    protected function copyDirectory($source, string $destination): void
    {
        if (! File::isDirectory($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        foreach (File::allFiles($source) as $item) {
            $this->processFile($item, $destination);
        }
    }

    /**
     * Copy directory with replacements applied to all files.
     *
     * @param  string  $source
     */
    protected function copyDirectoryWithReplacements($source, string $destination): void
    {
        if (! File::isDirectory($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        foreach (File::allFiles($source) as $item) {
            $this->processFileWithReplacements($item, $destination);
        }
    }

    /**
     * Process file.
     *
     * @param  object  $item
     */
    protected function processFile($item, string $destination): void
    {
        $relativePath = $item->getRelativePath();
        $targetInfo = $this->getTargetPathInfo($item, $destination, $relativePath);

        $this->ensureDirectoryExists($targetInfo['dir']);

        if ($item->isDir()) {
            $this->copyDirectory($item->getRealPath(), $targetInfo['path']);
        } else {
            $this->handleFileCopy($item, $targetInfo['path']);
        }
    }

    /**
     * Process file with replacements.
     *
     * @param  object  $item
     */
    protected function processFileWithReplacements($item, string $destination): void
    {
        $relativePath = $item->getRelativePath();
        $targetInfo = $this->getTargetPathInfo($item, $destination, $relativePath);

        $this->ensureDirectoryExists($targetInfo['dir']);

        if ($item->isDir()) {
            $this->copyDirectoryWithReplacements($item->getRealPath(), $targetInfo['path']);
        } else {
            // Always copy with replacements for downloaded files
            $this->copyFileWithReplacements($item->getRealPath(), $targetInfo['path']);
        }
    }

    /**
     * Get target path info.
     *
     * @param  object  $item
     */
    protected function getTargetPathInfo($item, string $destination, string $relativePath): array
    {
        $targetDir = $destination.($relativePath !== '' && $relativePath !== '0' ? '/'.$relativePath : '');
        $targetPath = $targetDir.'/'.$item->getFilename();
        $targetPath = preg_replace('/\.stub$/', '.php', $targetPath);

        if (str_starts_with($relativePath, 'app/')) {
            $relativePath = str_replace('app/Themes/', '', $relativePath);
            $targetDir = $this->theme->getThemeAppDir($relativePath);
            $targetPath = $targetDir.DIRECTORY_SEPARATOR.basename((string) $targetPath);
        }

        return [
            'dir' => $targetDir,
            'path' => $targetPath,
        ];
    }

    /**
     * Handle file copy.
     *
     * @param  object  $item
     */
    protected function handleFileCopy($item, string $targetPath): void
    {
        if (File::exists($targetPath) &&
            ! $this->option('force') &&
            ! $this->confirm("File {$targetPath} already exists. Do you want to overwrite it?")
        ) {
            return;
        }

        $this->copyFileWithReplacements($item->getRealPath(), $targetPath);
    }

    /**
     * Ensure directory exists.
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Copy file with replacements.
     *
     * @param  string  $sourcePath
     * @param  string  $destinationPath
     */
    protected function copyFileWithReplacements($sourcePath, $destinationPath): void
    {
        $extension = pathinfo((string) $destinationPath, PATHINFO_EXTENSION);

        if ($this->isTextFile($sourcePath, $extension)) {
            $content = File::get($sourcePath);
            $replacements = $this->getReplacements();

            // Apply placeholder replacements
            $content = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $content
            );

            // Write the modified content to the destination file
            File::put($destinationPath, $content);
        } else {
            // Simply copy non-text files without modification
            File::copy($sourcePath, $destinationPath);
        }
    }

    /**
     * Check if file is text file.
     *
     * @param  string  $filePath
     * @param  string  $extension
     */
    protected function isTextFile($filePath, $extension): bool
    {
        if (in_array(strtolower((string) $extension), $this->textExtensions)) {
            return true;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        $textMimeTypes = [
            'text/plain',
            'text/html',
            'text/css',
            'text/javascript',
            'application/javascript',
            'application/json',
            'application/xml',
            'application/x-httpd-php',
        ];

        return in_array($mimeType, $textMimeTypes) || str_starts_with($mimeType, 'text/');
    }

    /**
     * Get replacements.
     */
    protected function getReplacements(): array
    {
        return [
            '%theme_name%' => $this->theme->getName(),
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
     *
     * @return array
     */
    protected function promptForMissingOptionsUsing(): array
    {
        return [
            'theme-author' => [
                'label' => 'What is the author of the new theme?',
                'default' => 'Pollora',
                'validation' => 'required'
            ],
            'theme-author-uri' => [
                'label' => 'What is the URL of the theme author?',
                'default' => 'https://pollora.dev',
                'validation' => 'required|url'
            ],
            'theme-uri' => [
                'label' => 'What is the URL of the theme?',
                'default' => 'https://pollora.dev',
                'validation' => 'required|url'
            ],
            'theme-description' => [
                'label' => 'What is the description of the new theme?',
                'default' => 'A new theme using Pollora Framework',
                'validation' => 'required'
            ],
            'theme-version' => [
                'label' => 'What is the version of the theme?',
                'default' => '1.0',
                'validation' => 'required'
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
                validate: fn ($value): ?string => $this->validateValue($value)
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
            label: 'How would you like to create the theme?',
            options: [
                'repository' => 'Download from GitHub repository',
                'default' => 'Use default theme template',
            ],
            default: 'default'
        );

        if ($useRepository === 'repository') {
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
            $this->files->isDirectory($this->makeTheme($value)->getBasePath()) => "Theme \"{$value}\" already exists.",
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
     * Make theme.
     */
    protected function makeTheme(string $name): ThemeMetadata
    {
        return new ThemeMetadata($name, $this->getThemesPath());
    }

    /**
     * Get themes path.
     */
    protected function getThemesPath(): string
    {
        return $this->config->get('theme.directory', base_path('themes'));
    }
}
