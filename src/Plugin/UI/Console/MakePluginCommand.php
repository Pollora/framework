<?php

declare(strict_types=1);

namespace Pollora\Plugin\UI\Console;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Pollora\Modules\Infrastructure\Services\ModuleDownloader;
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
class MakePluginCommand extends Command implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pollora:make-plugin {name} {plugin_author} {plugin_author_uri} {plugin_uri} {plugin_description} {plugin_version} {--repository= : GitHub repository to download (owner/repo format)} {--repo-version= : Specific version/tag to download} {--force : Force create plugin with same name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate plugin structure by downloading from GitHub repository';

    /**
     * List of file extensions considered as text for replacements.
     *
     * @var array<int, string>
     */
    protected $textExtensions = ['php', 'js', 'css', 'html', 'htm', 'xml', 'txt', 'md', 'json', 'yaml', 'yml', 'svg', 'twig', 'blade.php', 'stub'];

    /**
     * The PluginMetadata instance representing the plugin being created.
     *
     * @var PluginMetadata
     */
    protected PluginMetadata $plugin;

    /**
     * Configuration repository.
     *
     * @var Repository
     */
    protected Repository $config;

    /**
     * Filesystem instance.
     *
     * @var Filesystem
     */
    protected Filesystem $files;

    /**
     * Create a new command instance.
     *
     * @param Repository $config Configuration repository
     * @param Filesystem $files Filesystem instance
     */
    public function __construct(Repository $config, Filesystem $files)
    {
        parent::__construct();
        $this->config = $config;
        $this->files = $files;
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

        if ($repository !== null && $repository !== '' && $repository !== '0') {
            $this->downloadFromRepository($repository);
        } else {
            // Use default repository instead of local stubs
            $this->downloadFromRepository('pollora/plugin-default');
        }

        $this->info("Plugin \"{$this->plugin->getName()}\" created successfully.");

        // Run npm install and npm run build in the plugin directory
        if (is_dir($this->plugin->getBasePath())) {
            $this->info('Running npm install and npm run build in '.$this->plugin->getBasePath().' ...');
            try {
                (new NpmRunner($this->plugin->getBasePath()))
                    ->install()
                    ->build();
                $this->info('npm install and build completed.');
            } catch (\Throwable $e) {
                $this->error('npm install or build failed: '.$e->getMessage());
                // Continue script even if npm fails
            }
        } else {
            $this->info('No plugin directory found at '.$this->plugin->getBasePath().', skipping npm install/build.');
        }

        // Prompt to activate this plugin
        $shouldActivate = select(
            label: 'Do you want to activate "'.$this->plugin->getName().'" plugin?',
            options: [
                'yes' => 'Yes',
                'no' => 'No',
            ],
            default: 'yes',
            hint: 'Selecting "Yes" will activate this plugin in WordPress.'
        );

        if ($shouldActivate === 'yes') {
            // Activate the plugin in WordPress (update the active_plugins option)
            if (function_exists('activate_plugin')) {
                $pluginBasename = $this->plugin->getBasename();
                $result = activate_plugin($pluginBasename);
                
                if (is_wp_error($result)) {
                    $this->warn('Unable to activate the plugin: '.$result->get_error_message());
                } else {
                    $this->info('Plugin "'.$this->plugin->getName().'" has been activated.');
                }
            } else {
                $this->warn('Unable to activate the plugin: WordPress functions are not available in this context.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Validate the plugin name.
     *
     * @return bool True if plugin name is valid
     */
    protected function validatePluginName(): bool
    {
        $message = $this->validateValue($this->argument('name'));
        if ($message !== null && $message !== '' && $message !== '0') {
            $this->error($message);
            return false;
        }

        return true;
    }

    /**
     * Check if the plugin can be generated.
     *
     * @return bool True if plugin can be generated
     */
    protected function canGeneratePlugin(): bool
    {
        if (! $this->directoryExists()) {
            return true;
        }

        $name = $this->plugin->getName();

        $this->error("Plugin \"{$name}\" already exists.");
        if ($this->option('force')) {
            return true;
        }

        return $this->confirm("Are you sure you want to override \"{$name}\" plugin folder?");
    }

    /**
     * Check if plugin directory exists.
     *
     * @return bool True if directory exists
     */
    protected function directoryExists(): bool
    {
        return $this->files->isDirectory($this->plugin->getBasePath());
    }

    /**
     * Download plugin from GitHub repository.
     *
     * @param string $repository Repository name
     * @return void
     */
    protected function downloadFromRepository(string $repository): void
    {
        $version = $this->option('repo-version');

        try {
            $downloader = new ModuleDownloader($repository);

            if ($version) {
                $downloader->setVersion($version);
            }

            $this->info("Downloading plugin from {$repository}".($version ? " (version: {$version})" : '').'...');

            $extractedPath = $downloader->downloadAndExtract($this->getPluginsPath());

            // Move contents from extracted folder to plugin folder
            $this->moveExtractedPlugin($extractedPath);

            $this->info('Plugin downloaded and extracted successfully.');

        } catch (\Exception $e) {
            $this->error("Failed to download plugin: {$e->getMessage()}");

            // Fallback to generating structure if download fails
            $this->warn('Falling back to generating default plugin structure...');
            $this->generatePluginStructure();
        }
    }

    /**
     * Move extracted plugin contents to the proper plugin directory.
     *
     * @param string $extractedPath Extracted path
     * @return void
     */
    protected function moveExtractedPlugin(string $extractedPath): void
    {
        $targetPath = $this->plugin->getBasePath();

        // Ensure target directory exists
        $this->ensureDirectoryExists($targetPath);

        // Move all contents from extracted path to target path with replacements
        $this->copyDirectoryWithReplacements($extractedPath, $targetPath);

        // Clean up the extracted directory
        $this->removeDirectory(dirname($extractedPath));
    }

    /**
     * Generate plugin structure from stubs.
     *
     * @return void
     */
    protected function generatePluginStructure(): void
    {
        $this->copyDirectory($this->getTemplatePath('common'), $this->plugin->getBasePath());
    }

    /**
     * Remove directory recursively.
     *
     * @param string $path Directory path
     * @return void
     */
    protected function removeDirectory(string $path): void
    {
        if (is_dir($path)) {
            File::deleteDirectory($path);
        }
    }

    /**
     * Copy directory with replacements applied to all files.
     *
     * @param string $source Source directory
     * @param string $destination Destination directory
     * @return void
     */
    protected function copyDirectoryWithReplacements(string $source, string $destination): void
    {
        if (! File::isDirectory($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        foreach (File::allFiles($source) as $item) {
            $this->processFileWithReplacements($item, $destination);
        }
    }

    /**
     * Copy directory.
     *
     * @param string $source Source directory
     * @param string $destination Destination directory
     * @return void
     */
    protected function copyDirectory(string $source, string $destination): void
    {
        if (! File::isDirectory($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        foreach (File::allFiles($source) as $item) {
            $this->processFile($item, $destination);
        }
    }

    /**
     * Process file with replacements.
     *
     * @param object $item File item
     * @param string $destination Destination directory
     * @return void
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
     * Process file.
     *
     * @param object $item File item
     * @param string $destination Destination directory
     * @return void
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
     * Get target path info.
     *
     * @param object $item File item
     * @param string $destination Destination directory
     * @param string $relativePath Relative path
     * @return array Target path information
     */
    protected function getTargetPathInfo($item, string $destination, string $relativePath): array
    {
        $targetDir = $destination.($relativePath !== '' && $relativePath !== '0' ? '/'.$relativePath : '');
        $filename = $item->getFilename();
        
        // Apply placeholder replacements to filename
        $replacements = $this->getReplacements();
        $filename = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $filename
        );
        
        $targetPath = $targetDir.'/'.$filename;
        $targetPath = preg_replace('/\.stub$/', '.php', $targetPath);

        if (str_starts_with($relativePath, 'app/')) {
            // Remove 'app/' prefix since getPluginAppDir() already adds it
            $subPath = substr($relativePath, 4); // Remove 'app/' prefix
            $subPath = str_replace('Plugins/', '', $subPath); // Remove 'Plugins/' if present
            $targetDir = $this->plugin->getPluginAppDir($subPath);
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
     * @param object $item File item
     * @param string $targetPath Target path
     * @return void
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
     *
     * @param string $directory Directory path
     * @return void
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
     * @param string $sourcePath Source file path
     * @param string $destinationPath Destination file path
     * @return void
     */
    protected function copyFileWithReplacements(string $sourcePath, string $destinationPath): void
    {
        $extension = pathinfo($destinationPath, PATHINFO_EXTENSION);

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
     * @param string $filePath File path
     * @param string $extension File extension
     * @return bool True if file is text file
     */
    protected function isTextFile(string $filePath, string $extension): bool
    {
        if (in_array(strtolower($extension), $this->textExtensions)) {
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
     *
     * @return array Replacement mappings
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
            '%plugin_author%' => $this->argument('plugin_author'),
            '%plugin_author_uri%' => $this->argument('plugin_author_uri'),
            '%plugin_uri%' => $this->argument('plugin_uri'),
            '%plugin_description%' => $this->argument('plugin_description'),
            '%plugin_version%' => $this->argument('plugin_version'),
            '%plugin_namespace%' => $this->plugin->getPluginNamespace(),
            '%plugin_slug%' => $this->plugin->getSlug(),
            '%plugin_basename%' => $this->plugin->getBasename(),
        ];
    }

    /**
     * Sanitize plugin name for use in PHP function names.
     *
     * @param string $pluginName Plugin name
     * @return string Sanitized function name
     */
    protected function sanitizeForPhpFunction(string $pluginName): string
    {
        // Convert to lowercase and replace non-alphanumeric characters with underscores
        $sanitized = strtolower($pluginName);
        $sanitized = preg_replace('/[^a-z0-9_]/', '_', $sanitized);
        
        // Remove multiple consecutive underscores
        $sanitized = preg_replace('/_+/', '_', $sanitized);
        
        // Remove leading/trailing underscores
        $sanitized = trim($sanitized, '_');
        
        // Ensure it doesn't start with a number by prefixing with underscore
        if (preg_match('/^[0-9]/', $sanitized)) {
            $sanitized = '_' . $sanitized;
        }
        
        return $sanitized;
    }

    /**
     * Prompt for missing arguments.
     *
     * @return array Prompt configuration
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => fn (): string => text(
                label: 'What is the name of the new plugin?',
                default: 'default-plugin',
                validate: fn ($value): ?string => $this->validateValue($value)
            ),
            'plugin_author' => fn (): string => text(
                label: 'What is the author of the new plugin?',
                default: 'Pollora',
                validate: 'required'
            ),
            'plugin_author_uri' => fn (): string => text(
                label: 'What is the URL of the plugin author?',
                default: 'https://pollora.dev',
                validate: 'required|url'
            ),
            'plugin_description' => fn (): string => text(
                label: 'What is the description of the new plugin?',
                default: 'A new plugin using Pollora Framework',
                validate: 'required'
            ),
            'plugin_uri' => fn (): string => text(
                label: 'What is the URL of the plugin?',
                default: 'https://pollora.dev',
                validate: 'required|url'
            ),
            'plugin_version' => fn (): string => text(
                label: 'What is the version of the plugin?',
                default: '1.0.0',
                validate: 'required'
            ),
        ];
    }

    /**
     * Prompt for repository if not provided.
     *
     * @return string|null Repository name or null
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
     *
     * @param string $value Value to validate
     * @return string|null Error message or null if valid
     */
    protected function validateValue(string $value): ?string
    {
        return match (true) {
            $value === '' || $value === '0' => 'Name is required.',
            preg_match('/[^a-zA-Z0-9\-_\s]/', $value) !== 0 && preg_match('/[^a-zA-Z0-9\-_\s]/', $value) !== false => 'Name must be alphanumeric, dash, space or underscore.',
            $this->files->isDirectory($this->makePlugin($value)->getBasePath()) => "Plugin \"{$value}\" already exists.",
            default => null,
        };
    }

    /**
     * Get template path.
     *
     * @param string $templateName Template name
     * @return string Template path
     */
    protected function getTemplatePath(string $templateName): string
    {
        return realpath(__DIR__.'/../../stubs/'.$templateName);
    }

    /**
     * Make plugin metadata instance.
     *
     * @param string $name Plugin name
     * @return PluginMetadata Plugin metadata
     */
    protected function makePlugin(string $name): PluginMetadata
    {
        return new PluginMetadata($name, $this->getPluginsPath());
    }

    /**
     * Get plugins path.
     *
     * @return string Plugins path
     */
    protected function getPluginsPath(): string
    {
        // Try WordPress constant first
        if (defined('WP_PLUGIN_DIR')) {
            return WP_PLUGIN_DIR;
        }

        // Fall back to configuration or WordPress default location
        return $this->config->get('plugin.path', public_path('content/plugins'));
    }
}