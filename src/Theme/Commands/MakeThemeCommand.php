<?php

declare(strict_types=1);

namespace Pollora\Theme\Commands;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pollora\Theme\ThemeMetadata;

use function Laravel\Prompts\text;

class MakeThemeCommand extends BaseThemeCommand implements PromptsForMissingInput
{
    protected $signature = 'theme:make {name} {theme_author} {theme_author_uri} {theme_uri} {theme_description} {theme_version} {--source= : Source folder to copy into the new theme} {--force : Force create theme with same name}';

    protected $description = 'Generate theme structure with the ability to copy an existing folder and replace strings';

    protected $textExtensions = ['php', 'js', 'css', 'html', 'htm', 'xml', 'txt', 'md', 'json', 'yaml', 'yml', 'svg', 'twig', 'blade.php', 'stub'];

    protected array $containerFolder;

    public function __construct(Repository $config, Filesystem $files)
    {
        parent::__construct($config, $files);
    }

    public function handle(): int
    {
        if (! $this->validateThemeName()) {
            return self::FAILURE;
        }

        if (! $this->canGenerateTheme()) {
            return self::FAILURE;
        }

        $this->setupContainerFolders();
        $this->generateThemeStructure();

        if ($this->option('source')) {
            $this->copySourceFolder();
        }

        $this->info("Theme \"{$this->getTheme()->getName()}\" created successfully.");

        return self::SUCCESS;
    }

    protected function validateThemeName(): bool
    {
        $message = $this->validateValue($this->argument('name'));
        if ($message !== null && $message !== '' && $message !== '0') {
            $this->error($message);

            return false;
        }

        return true;
    }

    protected function canGenerateTheme(): bool
    {
        if (! $this->directoryExists()) {
            return true;
        }

        $name = $this->getTheme()->getName();

        $this->error("Theme \"{$name}\" already exists.");
        if ($this->option('force')) {
            return true;
        }

        return $this->confirm("Are you sure you want to override \"{$name}\" theme folder?");
    }

    protected function setupContainerFolders(): void
    {
        $dirMapping = $this->config->get('theme.structure', []);
        $this->containerFolder = [
            'assets' => $dirMapping['assets'] ?? 'assets',
            'lang' => $dirMapping['lang'] ?? 'lang',
            'layout' => $dirMapping['layouts'] ?? 'views/layouts',
            'partial' => $dirMapping['partials'] ?? 'views/partials',
            'view' => $dirMapping['views'] ?? 'views',
        ];
    }

    protected function generateThemeStructure(): void
    {
        $this->copyDirectory($this->getTemplatePath('common'), $this->getTheme()->getBasePath());
    }

    protected function copySourceFolder(): void
    {
        $sourcePath = $this->option('source');
        if (! File::isDirectory($sourcePath)) {
            $this->error("The specified source folder does not exist: {$sourcePath}");

            return;
        }

        $destinationPath = $this->getTheme()->getBasePath();
        $this->info("Copying contents from {$sourcePath} to {$destinationPath}");
        $this->copyDirectory($sourcePath, $destinationPath);
        $this->info('Source folder contents copied successfully.');
    }

    protected function copyDirectory($source, string $destination): void
    {
        if (! File::isDirectory($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $items = File::allFiles($source);

        foreach ($items as $item) {
            $relativePath = $item->getRelativePath();
            $targetDir = $destination.($relativePath ? '/'.$relativePath : '');
            $targetPath = $targetDir.'/'.$item->getFilename();
            $targetPath = preg_replace('/\.stub$/', '.php', $targetPath);

            if (str_starts_with($relativePath, 'app/')) {
                $targetPath = $this->insertThemeNamespaceInPath($targetPath, $destination);
                $targetDir = dirname($targetPath);
            }

            if (! File::isDirectory($targetDir)) {
                File::makeDirectory($targetDir, 0755, true);
            }

            if ($item->isDir()) {
                $this->copyDirectory($item->getRealPath(), $targetPath);
            } else {
                if (File::exists($targetPath) && ! $this->option('force') && ! $this->confirm("File {$targetPath} already exists. Do you want to overwrite it?")) {
                    continue;
                }
                $this->copyFileWithReplacements($item->getRealPath(), $targetPath);
            }
        }
    }

    protected function insertThemeNamespaceInPath(string $path, string $themeLocacation): string
    {

        $path = str_replace([$themeLocacation, 'app/'], '', $path);
        $path = trim($path, '/');

        $segments = explode('/', $path, 2);

        if (count($segments) > 1) {
            $themeNamespace = $this->getThemeNamespace();

            return app_path($segments[0].'/'.$themeNamespace.'/'.$segments[1]);
        }

        return app_path($path);
    }

    protected function getThemeNamespace(): string
    {
        return Str::studly($this->getTheme()->getName());
    }

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

    protected function getReplacements(): array
    {
        return [
            '%theme_name%' => $this->getTheme()->getName(),
            '%theme_author%' => $this->argument('theme_author'),
            '%theme_author_uri%' => $this->argument('theme_author_uri'),
            '%theme_uri%' => $this->argument('theme_uri'),
            '%theme_description%' => $this->argument('theme_description'),
            '%theme_version%' => $this->argument('theme_version'),
            '%theme_namespace%' => $this->getThemeNamespace(),
        ];
    }

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

    protected function validateValue(string $value): ?string
    {
        return match (true) {
            $value === '' || $value === '0' => 'Name is required.',
            preg_match('/[^a-zA-Z0-9\-_\s]/', $value) !== 0 && preg_match('/[^a-zA-Z0-9\-_\s]/', $value) !== false => 'Name must be alphanumeric, dash, space or underscore.',
            $this->files->isDirectory($this->makeTheme($value)->getBasePath()) => "Theme \"{$value}\" already exists.",
            default => null,
        };
    }

    protected function getTemplatePath(string $templateName): string
    {
        if (in_array(realpath(__DIR__.'/../stubs/'.$templateName), ['', '0'], true) || realpath(__DIR__.'/../stubs/'.$templateName) === false) {
            dd($templateName);
        }

        return realpath(__DIR__.'/../stubs/'.$templateName);
    }

    protected function makeTheme(string $name): ThemeMetadata
    {
        return new ThemeMetadata($name, $this->getThemesPath());
    }

    protected function getThemesPath(): string
    {
        return $this->config->get('theme.directory', base_path('themes'));
    }
}
