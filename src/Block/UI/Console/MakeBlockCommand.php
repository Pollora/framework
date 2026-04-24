<?php

declare(strict_types=1);

namespace Pollora\Block\UI\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Pollora\Foundation\Console\Commands\Concerns\HasPluginSupport;
use Pollora\Foundation\Console\Commands\Concerns\HasThemeSupport;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Artisan command to scaffold a Gutenberg block in a theme or plugin.
 *
 * Generates block files (block.json, index.jsx, edit.jsx, save.jsx, CSS, etc.)
 * and bootstraps the Vite infrastructure on first use (vite.config.js patching,
 * npm dependencies, BlocksServiceProvider).
 */
class MakeBlockCommand extends Command
{
    use HasPluginSupport, HasThemeSupport;

    protected $name = 'pollora:make-block';

    protected $description = 'Create a new Gutenberg block in a theme or plugin';

    /**
     * npm dependencies to add for block development.
     */
    private const NPM_DEPENDENCIES = [
        '@roots/vite-plugin' => '^2.0.0',
        'glob' => '^11.0.0',
        '@wordpress/blocks' => '^14.0.0',
        '@wordpress/block-editor' => '^14.0.0',
        '@wordpress/components' => '^29.0.0',
        '@wordpress/element' => '^6.0.0',
        '@wordpress/i18n' => '^5.0.0',
    ];

    public function handle(): int
    {
        $name = $this->argument('name');

        if (! preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
            $this->components->error("Invalid block name \"{$name}\". Must be kebab-case (e.g. hero-banner).");

            return self::FAILURE;
        }

        // Validate mutually exclusive options
        if ($this->hasPluginOption() && $this->hasThemeOption()) {
            $this->components->error('Options --theme and --plugin are mutually exclusive.');

            return self::FAILURE;
        }

        $target = $this->resolveTarget();

        if ($target === null) {
            return self::FAILURE;
        }

        $blocksDir = $target['path'].'/resources/blocks';
        $blockDir = $blocksDir.'/'.$name;

        // Check if block already exists
        if (is_dir($blockDir) && ! $this->option('force')) {
            if (! $this->components->confirm("Block \"{$name}\" already exists. Overwrite?")) {
                return self::FAILURE;
            }
        }

        $isFirstBlock = ! is_dir($blocksDir) || $this->isEmptyDirectory($blocksDir);

        // Bootstrap infrastructure if first block
        if ($isFirstBlock) {
            $this->bootstrapInfrastructure($target);
        }

        // Scaffold the block
        $this->scaffoldBlock($name, $blockDir, $target);

        $namespace = $this->option('namespace') ?? $target['slug'];
        $this->components->info("Block [{$namespace}/{$name}] created in {$blockDir}/");

        if ($isFirstBlock) {
            $this->newLine();
            $this->components->info('Next steps:');
            $this->line("  1. Run: cd {$target['path']} && npm install");
            $this->line('  2. Run: npm run dev (for HMR) or npm run build');
            $this->line("  3. Your block will appear in the editor under \"{$this->option('category')}\" category");
        }

        return self::SUCCESS;
    }

    /**
     * Resolve the target theme or plugin.
     *
     * @return array{type: string, path: string, slug: string, namespace: string, containerName: string}|null
     */
    private function resolveTarget(): ?array
    {
        if ($this->hasPluginOption()) {
            $plugin = $this->resolvePlugin();

            if (! $plugin) {
                $this->components->error('Plugin name is required with --plugin option.');

                return null;
            }

            $path = $this->getPluginPath();

            if (! is_dir($path)) {
                $this->components->error("Plugin directory not found: {$path}");

                return null;
            }

            return [
                'type' => 'plugin',
                'path' => $path,
                'slug' => $plugin,
                'namespace' => $this->getPluginSourceNamespace().'Providers',
                'containerName' => 'plugin.'.$plugin,
            ];
        }

        // Default to theme
        $theme = $this->resolveTheme() ?? $this->getActiveTheme();

        if (! $theme) {
            $this->components->error('No theme specified and no active theme found.');

            return null;
        }

        $path = $this->getThemePath($theme);

        if (! is_dir($path)) {
            $this->components->error("Theme directory not found: {$path}");

            return null;
        }

        return [
            'type' => 'theme',
            'path' => $path,
            'slug' => $theme,
            'namespace' => $this->getThemeSourceNamespace($theme).'Providers',
            'containerName' => 'theme',
        ];
    }

    /**
     * Bootstrap the block infrastructure for the first block in a target.
     */
    private function bootstrapInfrastructure(array $target): void
    {
        $this->createBlocksServiceProvider($target);
        $this->patchViteConfig($target);
        $this->addNpmDependencies($target);
    }

    /**
     * Create the BlocksServiceProvider in the target.
     */
    private function createBlocksServiceProvider(array $target): void
    {
        $providerPath = $target['path'].'/app/Providers/BlocksServiceProvider.php';

        if (file_exists($providerPath)) {
            $this->components->warn('BlocksServiceProvider already exists, skipping.');

            return;
        }

        $stub = $this->getStubContent('blocks-service-provider.php');
        $stub = str_replace(
            ['{{ namespace }}', '{{ containerName }}'],
            [$target['namespace'], $target['containerName']],
            $stub
        );

        $this->ensureDirectoryExists(dirname($providerPath));
        file_put_contents($providerPath, $stub);

        $this->components->twoColumnDetail('BlocksServiceProvider', 'CREATED');
    }

    /**
     * Patch vite.config.js to add block support.
     */
    private function patchViteConfig(array $target): void
    {
        $viteConfigPath = $target['path'].'/vite.config.js';

        if (! file_exists($viteConfigPath)) {
            $this->components->warn('vite.config.js not found. You will need to configure Vite manually.');
            $this->displayManualViteInstructions();

            return;
        }

        $content = file_get_contents($viteConfigPath);

        // Check if already patched
        if (str_contains($content, '@roots/vite-plugin') || str_contains($content, 'blockEntries')) {
            $this->components->twoColumnDetail('vite.config.js', 'ALREADY CONFIGURED');

            return;
        }

        // Check for standard Pollora pattern
        if (! str_contains($content, 'getThemeConfig') && ! str_contains($content, 'laravel-vite-plugin')) {
            $this->components->warn('vite.config.js does not match expected Pollora pattern.');
            $this->displayManualViteInstructions();

            return;
        }

        $patched = $this->applyViteConfigPatches($content);

        if ($patched === null) {
            $this->components->warn('Could not automatically patch vite.config.js.');
            $this->displayManualViteInstructions();

            return;
        }

        file_put_contents($viteConfigPath, $patched);
        $this->components->twoColumnDetail('vite.config.js', 'UPDATED');
    }

    /**
     * Apply patches to vite.config.js content.
     */
    private function applyViteConfigPatches(string $content): ?string
    {
        // 1. Add imports after existing imports
        $rootsImport = "import { wordpressPlugin } from '@roots/vite-plugin';";
        $globImport = "import { globSync } from 'glob';";

        $imports = [];
        if (! str_contains($content, '@roots/vite-plugin')) {
            $imports[] = $rootsImport;
        }
        if (! str_contains($content, 'globSync')) {
            $imports[] = $globImport;
        }

        if ($imports !== []) {
            // Find the last import statement and add after it
            if (preg_match('/^(import\s+.+?[\'"];?\s*$)/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
                // Find the last import
                preg_match_all('/^import\s+.+?[\'"];?\s*$/m', $content, $allMatches, PREG_OFFSET_CAPTURE);
                $lastImport = end($allMatches[0]);
                $insertPos = $lastImport[1] + strlen($lastImport[0]);
                $content = substr($content, 0, $insertPos)."\n".implode("\n", $imports).substr($content, $insertPos);
            }
        }

        // 2. Add block entries discovery after imports (before export/function)
        $blockEntriesCode = <<<'JS'

const blockEntries = globSync('./resources/blocks/*/index.{js,jsx,ts,tsx}')
    .reduce((acc, file) => {
        const slug = path.basename(path.dirname(file));
        acc[`blocks/${slug}`] = file;
        return acc;
    }, {});
const hasBlocks = Object.keys(blockEntries).length > 0;
JS;

        if (! str_contains($content, 'blockEntries')) {
            // Insert before the first export or function declaration
            if (preg_match('/^(export\s|function\s|const\s+\w+\s*=\s*\()/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $insertPos = $matches[0][1];
                $content = substr($content, 0, $insertPos).$blockEntriesCode."\n\n".substr($content, $insertPos);
            }
        }

        // 3. Add block entries to input array
        // Look for input: ["./resources/assets/app.js"] or similar patterns
        if (preg_match('/(input:\s*\[)([^\]]+)(\])/', $content, $matches)) {
            $currentInput = $matches[2];
            if (! str_contains($currentInput, 'blockEntries')) {
                $content = str_replace(
                    $matches[0],
                    $matches[1].$currentInput.', ...Object.values(blockEntries)'.$matches[3],
                    $content
                );
            }
        }

        // 4. Add wordpressPlugin() to plugins array
        if (! str_contains($content, 'wordpressPlugin')) {
            // Find plugins: [ ... ] and add before the closing bracket
            if (preg_match('/(plugins:\s*\[)(.*?)(\])/s', $content, $matches)) {
                $existingPlugins = rtrim($matches[2]);
                $separator = $existingPlugins !== '' ? ",\n        " : "\n        ";
                $content = str_replace(
                    $matches[0],
                    $matches[1].$matches[2].$separator.'...(hasBlocks ? [wordpressPlugin()] : [])'.$separator.$matches[3],
                    $content
                );
            }
        }

        // 5. Add resources/blocks/** to refresh paths if present
        if (str_contains($content, 'refresh') && ! str_contains($content, 'resources/blocks')) {
            $content = preg_replace(
                "/(refresh:\s*\[)([^\]]*?)(\])/",
                '$1$2, \'resources/blocks/**\'$3',
                $content
            );
        }

        return $content;
    }

    /**
     * Display manual Vite configuration instructions.
     */
    private function displayManualViteInstructions(): void
    {
        $this->newLine();
        $this->line('Add the following to your vite.config.js:');
        $this->newLine();
        $this->line("  import { wordpressPlugin } from '@roots/vite-plugin';");
        $this->line("  import { globSync } from 'glob';");
        $this->newLine();
        $this->line('  // After imports:');
        $this->line("  const blockEntries = globSync('./resources/blocks/*/index.{js,jsx,ts,tsx}')");
        $this->line('    .reduce((acc, file) => {');
        $this->line('      const slug = path.basename(path.dirname(file));');
        $this->line('      acc[`blocks/${slug}`] = file;');
        $this->line('      return acc;');
        $this->line('    }, {});');
        $this->newLine();
        $this->line('  // In input: [..., ...Object.values(blockEntries)]');
        $this->line('  // In plugins: [...(Object.keys(blockEntries).length > 0 ? [wordpressPlugin()] : [])]');
        $this->newLine();
    }

    /**
     * Add npm dependencies to package.json.
     */
    private function addNpmDependencies(array $target): void
    {
        $packageJsonPath = $target['path'].'/package.json';

        if (! file_exists($packageJsonPath)) {
            $this->components->error("package.json not found in {$target['path']}. Run npm init -y first.");

            return;
        }

        $package = json_decode(file_get_contents($packageJsonPath), true);
        $devDeps = $package['devDependencies'] ?? [];
        $added = [];

        foreach (self::NPM_DEPENDENCIES as $dep => $version) {
            if (! isset($devDeps[$dep])) {
                $devDeps[$dep] = $version;
                $added[] = $dep;
            }
        }

        if ($added === []) {
            $this->components->twoColumnDetail('package.json', 'ALREADY UP TO DATE');

            return;
        }

        ksort($devDeps);
        $package['devDependencies'] = $devDeps;

        file_put_contents(
            $packageJsonPath,
            json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->components->twoColumnDetail('package.json', 'UPDATED (+'.implode(', ', $added).')');
    }

    /**
     * Scaffold the block files from stubs.
     */
    private function scaffoldBlock(string $name, string $blockDir, array $target): void
    {
        $this->ensureDirectoryExists($blockDir);

        $namespace = $this->option('namespace') ?? $target['slug'];
        $title = $this->option('title') ?? Str::title(str_replace('-', ' ', $name));
        $isDynamic = $this->option('dynamic');
        $hasInnerBlocks = $this->option('inner-blocks');
        $hasViewScript = ! $this->option('no-view-script');

        $replacements = [
            '{{ blockSlug }}' => $name,
            '{{ blockNamespace }}' => $namespace,
            '{{ blockFullName }}' => $namespace.'/'.$name,
            '{{ title }}' => $title,
            '{{ category }}' => $this->option('category'),
            '{{ icon }}' => $this->option('icon'),
            '{{ className }}' => Str::studly($name),
            '{{ targetSlug }}' => $target['slug'],
            '{{ blockCssClass }}' => str_replace('/', '-', $namespace.'-'.$name),
        ];

        // block.json — may need conditional additions
        $blockJson = $this->getStubContent('block.json');
        $blockJson = $this->replaceStubPlaceholders($blockJson, $replacements);
        $blockJsonData = json_decode($blockJson, true);

        if ($isDynamic) {
            $blockJsonData['render'] = 'file:./render.php';
        }

        if ($hasViewScript) {
            $blockJsonData['viewScript'] = 'file:./view.js';
        }

        file_put_contents(
            $blockDir.'/block.json',
            json_encode($blockJsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        // index.jsx
        $indexStub = $this->getStubContent('index.jsx');
        if ($isDynamic) {
            // Remove save import and replace save reference
            $indexStub = str_replace("import save from './save';\n", '', $indexStub);
            $indexStub = str_replace(
                '    save,',
                '    save: () => null,',
                $indexStub
            );
        }
        $this->writeStub($blockDir.'/index.jsx', $indexStub, $replacements);

        // edit.jsx
        $editStub = $hasInnerBlocks ? 'edit-inner-blocks.jsx' : 'edit.jsx';
        $this->writeStub($blockDir.'/edit.jsx', $this->getStubContent($editStub), $replacements);

        // save.jsx (only for static blocks)
        if (! $isDynamic) {
            $saveStub = $hasInnerBlocks ? 'save-inner-blocks.jsx' : 'save.jsx';
            $this->writeStub($blockDir.'/save.jsx', $this->getStubContent($saveStub), $replacements);
        }

        // render.php (only for dynamic blocks)
        if ($isDynamic) {
            $this->writeStub($blockDir.'/render.php', $this->getStubContent('render.php'), $replacements);
        }

        // CSS files
        $this->writeStub($blockDir.'/editor.css', $this->getStubContent('editor.css'), $replacements);
        $this->writeStub($blockDir.'/style.css', $this->getStubContent('style.css'), $replacements);

        // view.js (unless --no-view-script)
        if ($hasViewScript) {
            $this->writeStub($blockDir.'/view.js', $this->getStubContent('view.js'), $replacements);
        }
    }

    /**
     * Read a stub file content.
     */
    private function getStubContent(string $stubName): string
    {
        // Check for published stubs first
        $publishedPath = base_path("stubs/pollora-block/{$stubName}.stub");

        if (file_exists($publishedPath)) {
            return file_get_contents($publishedPath);
        }

        return file_get_contents(dirname(__DIR__, 2)."/stubs/{$stubName}.stub");
    }

    /**
     * Write a stub file with placeholder replacements.
     */
    private function writeStub(string $path, string $content, array $replacements): void
    {
        file_put_contents($path, $this->replaceStubPlaceholders($content, $replacements));
    }

    /**
     * Replace placeholders in stub content.
     */
    private function replaceStubPlaceholders(string $content, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Ensure a directory exists.
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Check if a directory is empty.
     */
    private function isEmptyDirectory(string $directory): bool
    {
        if (! is_dir($directory)) {
            return true;
        }

        $iterator = new \FilesystemIterator($directory);

        return ! $iterator->valid();
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'Block slug in kebab-case (e.g. hero-banner)'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ...($this->getThemeOptions()),
            ...($this->getPluginOptions()),
            ['namespace', null, InputOption::VALUE_REQUIRED, 'Block namespace (before the /)'],
            ['title', null, InputOption::VALUE_REQUIRED, 'Block title in the inserter'],
            ['category', null, InputOption::VALUE_REQUIRED, 'Gutenberg category', 'widgets'],
            ['icon', null, InputOption::VALUE_REQUIRED, 'Dashicon name', 'block-default'],
            ['dynamic', null, InputOption::VALUE_NONE, 'Create a dynamic block with render.php'],
            ['inner-blocks', null, InputOption::VALUE_NONE, 'Add InnerBlocks support'],
            ['no-view-script', null, InputOption::VALUE_NONE, 'Do not generate a frontend view script'],
            ['force', null, InputOption::VALUE_NONE, 'Overwrite existing block without confirmation'],
        ];
    }
}
