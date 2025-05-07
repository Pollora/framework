<?php

/**
 * Class MakeThemeCommand
 *
 * Artisan command to scaffold a new theme directory structure, optionally copy a source folder,
 * perform string replacements, run npm install/build, and optionally set the theme as the active WordPress theme.
 */
declare(strict_types=1);

namespace Pollora\Theme\Commands;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Pollora\Support\NpmRunner;
use Pollora\Theme\ThemeMetadata;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakeThemeCommand extends BaseThemeCommand implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pollora:make-theme {name} {theme_author} {theme_author_uri} {theme_uri} {theme_description} {theme_version} {--source= : Source folder to copy into the new theme} {--force : Force create theme with same name} {--repo= : GitHub repository URL to use instead of the default}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate theme structure with the ability to copy an existing folder and replace strings';

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

        $repo = $this->option('repo');
        $slug = $this->argument('name');
        if (!$repo) {
            $repo = "https://github.com/Pollora/theme-{$slug}.git";
        }
        $destination = $this->theme->getBasePath();
        $this->info("Cloning theme from $repo ...");
        try {
            $output = null;
            $result = null;
            @mkdir($destination, 0755, true);
            exec("git clone --depth=1 " . escapeshellarg($repo) . " " . escapeshellarg($destination), $output, $result);
            if ($result !== 0) {
                $this->error("Failed to clone repository: $repo");
                return self::FAILURE;
            }
            // Nettoyer le dossier .git pour ne pas embarquer l'historique
            if (is_dir($destination . '/.git')) {
                exec('rm -rf ' . escapeshellarg($destination . '/.git'));
            }
            $this->info("Theme \"{$this->theme->getName()}\" cloned successfully from $repo.");

            // Appliquer la structure et les remplacements
            $this->setupContainerFolders();
            $this->applyReplacementsToDirectory($destination);
            if ($this->option('source')) {
                $this->copySourceFolder();
            }
        } catch (\Throwable $e) {
            $this->error('Error cloning theme: ' . $e->getMessage());
            return self::FAILURE;
        }


        // npm install/build si frontend
        if (is_dir($this->theme->getBasePath())) {
            $this->info('Running npm install and npm run build in ' . $this->theme->getBasePath() . ' ...');
            try {
                (new NpmRunner($this->theme->getBasePath()))
                    ->install()
                    ->build();
                $this->info('npm install and build completed.');
            } catch (\Throwable $e) {
                $this->error('npm install or build failed: ' . $e->getMessage());
                // Continue script even if npm fails
            }
        } else {
            $this->info('No frontend directory found at ' . $this->theme->getBasePath() . ', skipping npm install/build.');
        }

        // Prompt to set this theme as the active WordPress theme
        $shouldSetActive = select(
            label: 'Do you want to set "' . $this->theme->getName() . '" as the active WordPress theme?',
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
                $this->info('Theme "' . $this->theme->getName() . '" is now set as the active WordPress theme.');
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
            'assets' => $dirMapping['assets'] ?? 'assets',
            'lang' => $dirMapping['lang'] ?? 'lang',
            'layout' => $dirMapping['layouts'] ?? 'views/layouts',
            'partial' => $dirMapping['partials'] ?? 'views/partials',
            'view' => $dirMapping['views'] ?? 'views',
        ];

        return $this;
    }

    /**
     * Generate theme structure.
     */
    protected function generateThemeStructure(): void
    {
        $this->copyDirectory($this->getTemplatePath('common'), $this->theme->getBasePath());
    }

    /**
     * Copy source folder.
     */
    protected function copySourceFolder(): void
    {
        $sourcePath = $this->option('source');
        if (! File::isDirectory($sourcePath)) {
            $this->error("The specified source folder does not exist: {$sourcePath}");

            return;
        }

        $destinationPath = $this->theme->getBasePath();
        $this->info("Copying contents from {$sourcePath} to {$destinationPath}");
        $this->copyDirectory($sourcePath, $destinationPath);
        $this->info('Source folder contents copied successfully.');
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

            // Appliquer les remplacements
            $content = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $content
            );

            // Écrire le contenu modifié dans le fichier de destination
            File::put($destinationPath, $content);
        } else {
            // Copier simplement les fichiers non textuels sans modification
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
            '%theme_author%' => $this->argument('theme_author'),
            '%theme_author_uri%' => $this->argument('theme_author_uri'),
            '%theme_uri%' => $this->argument('theme_uri'),
            '%theme_description%' => $this->argument('theme_description'),
            '%theme_version%' => $this->argument('theme_version'),
            '%theme_namespace%' => $this->theme->getThemeNamespace(),
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
            'theme_author' => fn (): string => text(
                label: 'What is the author of the new theme?',
                default: 'Pollora',
                validate: 'required'
            ),
            'theme_author_uri' => fn (): string => text(
                label: 'What is the URL of the theme author?',
                default: 'https://pollora.dev',
                validate: 'required|url'
            ),
            'theme_description' => fn (): string => text(
                label: 'What is the description of the new theme?',
                default: 'A new theme using Pollora Framework',
                validate: 'required'
            ),
            'theme_uri' => fn (): string => text(
                label: 'What is the URL of the theme?',
                default: 'https://pollora.dev',
                validate: 'required|url'
            ),
            'theme_version' => fn (): string => text(
                label: 'What is the version of the theme?',
                default: '1.0',
                validate: 'required'
            ),
        ];
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
        return realpath(__DIR__.'/../stubs/'.$templateName);
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

    /**
     * Applique les remplacements dans tous les fichiers du dossier donné.
     */
    protected function applyReplacementsToDirectory(string $directory): void
    {
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }
            $path = $file->getPathname();
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            if ($this->isTextFile($path, $extension)) {
                $content = file_get_contents($path);
                $replacements = $this->getReplacements();
                $content = str_replace(array_keys($replacements), array_values($replacements), $content);
                file_put_contents($path, $content);
            }
        }
    }
}
