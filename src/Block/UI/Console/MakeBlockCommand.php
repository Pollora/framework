<?php

declare(strict_types=1);

namespace Pollora\Block\UI\Console;

use Illuminate\Console\Attributes\Aliases;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Pollora\Foundation\Console\Commands\Concerns\HasPluginSupport;
use Pollora\Foundation\Console\Commands\Concerns\HasThemeSupport;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Artisan command to scaffold a Gutenberg block in a theme or plugin.
 *
 * Generates block files in `resources/views/blocks/{slug}` (block.json, index.jsx,
 * edit.jsx, render.blade.php, CSS, etc.) and bootstraps the Vite infrastructure on
 * first use (vite.config.js patching, npm dependencies, BlocksServiceProvider).
 *
 * Blocks are dynamic and rendered with Blade by default: their markup is not stored
 * in post_content, so changing it never invalidates existing content. `--static`
 * generates a save.jsx instead.
 */
#[Description('Create a new Gutenberg block in a theme or plugin')]
#[Aliases(['pollora:make-block'])]
class MakeBlockCommand extends Command
{
    use HasPluginSupport;
    use HasThemeSupport;

    protected $name = 'pollora:make:block';

    /**
     * npm dependencies to add for block development.
     */
    private const array NPM_DEPENDENCIES = [
        '@roots/vite-plugin' => '^2.0.0',
        'glob' => '^11.0.0',
        '@wordpress/blocks' => '^14.0.0',
        '@wordpress/block-editor' => '^14.0.0',
        '@wordpress/components' => '^29.0.0',
        '@wordpress/element' => '^6.0.0',
        '@wordpress/i18n' => '^5.0.0',
    ];

    /**
     * Blocks directory, relative to the theme or plugin root.
     */
    private const string BLOCKS_DIRECTORY = 'resources/views/blocks';

    /**
     * Blocks directory used before v13.32, relative to the theme or plugin root.
     */
    private const string LEGACY_BLOCKS_DIRECTORY = 'resources/blocks';

    /**
     * Refresh glob reloading the page for Blade views only: a broader resources/views/**
     * would turn every block JSX change into a full reload instead of HMR.
     */
    private const string BLADE_REFRESH_PATH = 'resources/views/**/*.blade.php';

    public function handle(): int
    {
        $name = $this->argument('name');

        if (! preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
            $this->components->error(sprintf('Invalid block name "%s". Must be kebab-case (e.g. hero-banner).', $name));

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

        if ($this->option('dynamic')) {
            $this->components->warn('--dynamic is deprecated: blocks are dynamic by default. Use --static for a block saved in post content.');
        }

        $blocksDir = $target['path'].'/'.self::BLOCKS_DIRECTORY;
        $blockDir = $blocksDir.'/'.$name;

        // Check if block already exists
        if (is_dir($blockDir) && ! $this->option('force') && ! $this->components->confirm(sprintf('Block "%s" already exists. Overwrite?', $name))) {
            return self::FAILURE;
        }

        // A target with blocks in the former location is already bootstrapped
        $isFirstBlock = $this->isEmptyDirectory($blocksDir)
            && $this->isEmptyDirectory($target['path'].'/'.self::LEGACY_BLOCKS_DIRECTORY);

        // Bootstrap infrastructure if first block
        if ($isFirstBlock) {
            $this->bootstrapInfrastructure($target);
        } else {
            $this->upgradeViteConfig($target);
            $this->ensureBlocksServiceProviderExists($target);
        }

        // Scaffold the block
        $this->scaffoldBlock($name, $blockDir, $target);

        $namespace = $this->option('namespace') ?? $target['slug'];
        $this->components->info(sprintf('Block [%s/%s] created in %s/', $namespace, $name, $blockDir));

        if ($isFirstBlock) {
            $this->newLine();
            $this->components->info('Next steps:');
            $this->line(sprintf('  1. Run: cd %s && npm install', $target['path']));
            $this->line('  2. Run: npm run dev (for HMR) or npm run build');
            $this->line(sprintf('  3. Your block will appear in the editor under "%s" category', $this->option('category')));
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
                $this->components->error('Plugin directory not found: '.$path);

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
            $this->components->error('Theme directory not found: '.$path);

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
     * Write the BlocksServiceProvider when the target has none.
     *
     * A blocks directory that is not empty used to be taken as proof that the
     * infrastructure was already in place. It usually is — but not always.
     * Blocks registered by something else live there too: an ACF block, a
     * block placed by hand, a block from a theme written before the provider
     * existed. None of those needs the BlocksServiceProvider that registers
     * Vite-built blocks, so a target can hold blocks and have no provider.
     *
     * In such a target every block ever scaffolded was written to disk, built
     * by Vite, and registered by nobody — nothing failed, nothing reached the
     * log, and the block simply never appeared in the editor. Measured on
     * theme-apiary, whose only shipped block is an ACF one.
     *
     * Only the provider is created here. The rest of the bootstrap — npm
     * dependencies, the initial vite.config.js patch — belongs to a genuinely
     * first block and is left alone.
     */
    private function ensureBlocksServiceProviderExists(array $target): void
    {
        if (file_exists($this->blocksServiceProviderPath($target))) {
            return;
        }

        $this->createBlocksServiceProvider($target);
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
     * Where the BlocksServiceProvider goes: the target's source directory,
     * `app/` or `src/`, whichever its autoloader maps.
     *
     * The stub reaches the blocks with `dirname(__DIR__, 2)`, which lands on
     * the target root from either directory.
     *
     * @param  array{path: string}  $target
     */
    private function blocksServiceProviderPath(array $target): string
    {
        return $this->resolveSourceDirectory($target['path']).'/Providers/BlocksServiceProvider.php';
    }

    /**
     * Create the BlocksServiceProvider in the target.
     */
    private function createBlocksServiceProvider(array $target): void
    {
        $providerPath = $this->blocksServiceProviderPath($target);

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

        $content = (string) file_get_contents($viteConfigPath);

        // Check if already patched
        if (str_contains($content, '@roots/vite-plugin') || str_contains($content, 'blockEntries')) {
            $this->upgradeViteConfig($target);

            return;
        }

        // Check for standard Pollora pattern
        if (! str_contains($content, 'getThemeConfig') && ! str_contains($content, 'laravel-vite-plugin')) {
            $this->components->warn('vite.config.js does not match expected Pollora pattern.');
            $this->displayManualViteInstructions();

            return;
        }

        file_put_contents($viteConfigPath, $this->applyViteConfigPatches($content));
        $this->components->twoColumnDetail('vite.config.js', 'UPDATED');
    }

    /**
     * Apply patches to vite.config.js content.
     */
    private function applyViteConfigPatches(string $content): string
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

        // Find the last import statement and add after it
        if ($imports !== [] && preg_match('/^(import\s+.+?[\'"];?\s*$)/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            // Find the last import
            preg_match_all('/^import\s+.+?[\'"];?\s*$/m', $content, $allMatches, PREG_OFFSET_CAPTURE);
            $lastImport = end($allMatches[0]);
            $insertPos = $lastImport[1] + strlen($lastImport[0]);
            $content = substr($content, 0, $insertPos)."\n".implode("\n", $imports).substr($content, $insertPos);
        }

        // 2. Add block entries discovery after imports (before export/function)
        $blockEntriesCode = "\n".$this->blockEntriesCode(includeLegacy: false)."\nconst hasBlocks = Object.keys(blockEntries).length > 0;";

        // Insert before the first export or function declaration
        if (! str_contains($content, 'blockEntries') && preg_match('/^(export\s|function\s|const\s+\w+\s*=\s*\()/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $insertPos = $matches[0][1];
            $content = substr($content, 0, $insertPos).$blockEntriesCode."\n\n".substr($content, $insertPos);
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
        // Find plugins: [ ... ] and add before the closing bracket
        // Look for the call, not the name: step 1 already imported wordpressPlugin
        if (! str_contains($content, 'wordpressPlugin(') && preg_match('/(plugins:\s*\[)(.*?)(\])/s', $content, $matches)) {
            $existingPlugins = rtrim($matches[2]);
            $separator = $existingPlugins !== '' ? ",\n        " : "\n        ";
            $content = str_replace(
                $matches[0],
                $matches[1].$matches[2].$separator.'...(hasBlocks ? [wordpressPlugin()] : [])'.$separator.$matches[3],
                $content
            );
        }

        // 5. Reload the page for Blade views only, so block JSX keeps HMR
        return $this->patchRefreshPaths($content);
    }

    /**
     * Point an already configured vite.config.js at resources/views/blocks.
     *
     * Configs written before v13.32 only glob resources/blocks, and the one generated
     * by this command only built index scripts, leaving view scripts and stylesheets
     * out of the production manifest. Their blockEntries declaration is replaced by
     * one building both locations.
     */
    private function upgradeViteConfig(array $target): void
    {
        $viteConfigPath = $target['path'].'/vite.config.js';

        if (! file_exists($viteConfigPath)) {
            return;
        }

        $content = (string) file_get_contents($viteConfigPath);

        if (str_contains($content, self::BLOCKS_DIRECTORY)) {
            $this->components->twoColumnDetail('vite.config.js', 'ALREADY CONFIGURED');

            return;
        }

        $upgraded = preg_replace(
            '/const blockEntries = globSync\(.*?\n\s*\}, \{\}\);/s',
            $this->blockEntriesCode(includeLegacy: true),
            $content,
            1,
            $count
        );

        if (! is_string($upgraded) || $count === 0) {
            $this->components->warn(sprintf('Could not update vite.config.js: make its block entries glob ./%s.', self::BLOCKS_DIRECTORY));

            return;
        }

        file_put_contents($viteConfigPath, $this->patchRefreshPaths($upgraded));
        $this->components->twoColumnDetail('vite.config.js', 'UPDATED (resources/views/blocks)');
    }

    /**
     * JavaScript declaring the Vite entries of every block script and stylesheet.
     */
    private function blockEntriesCode(bool $includeLegacy): string
    {
        $patterns = [
            sprintf("    './%s/*/{index,view}.{js,jsx,ts,tsx}',", self::BLOCKS_DIRECTORY),
            sprintf("    './%s/*/{editor,style}.css',", self::BLOCKS_DIRECTORY),
        ];

        if ($includeLegacy) {
            $patterns[] = sprintf('    // Deprecated location, built until its blocks move to %s', self::BLOCKS_DIRECTORY);
            $patterns[] = sprintf("    './%s/*/{index,view}.{js,jsx,ts,tsx}',", self::LEGACY_BLOCKS_DIRECTORY);
            $patterns[] = sprintf("    './%s/*/{editor,style}.css',", self::LEGACY_BLOCKS_DIRECTORY);
        }

        return "const blockEntries = globSync([\n".implode("\n", $patterns)."\n])\n"
            ."    .reduce((acc, file) => {\n"
            ."        // Keyed by path: blocks sharing a file name never overwrite each other\n"
            ."        acc[file.replace(/^\\.\\//, '').replace(/\\.\\w+$/, '')] = file;\n"
            ."        return acc;\n"
            .'    }, {});';
    }

    /**
     * Restrict Vite full-page reloads under resources/views to Blade templates.
     *
     * laravel-vite-plugin's refreshPaths, a literal resources/views/** and the
     * resources/blocks/** added by earlier versions of this command all reload the
     * page on a block JSX change, defeating HMR. Globs resolve from the Vite root, so
     * Blade views are reloaded through resources/views/**\/*.blade.php.
     */
    private function patchRefreshPaths(string $content): string
    {
        if (! preg_match('/refresh:\s*\[(.*?)\]/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        $original = $matches[1][0];
        $paths = rtrim($original);
        $trailingWhitespace = substr($original, strlen($paths));

        $paths = (string) preg_replace('/\s*[\'"]resources\/blocks\/\*\*[\'"],?/', '', $paths);
        // The lookbehind leaves the refreshPaths filter below untouched when run twice
        $paths = (string) preg_replace('/(?<!!== )([\'"])([^\'"]*resources\/views\/)\*\*\1/', '$1$2**/*.blade.php$1', $paths);
        $paths = (string) preg_replace(
            '/\.\.\.refreshPaths(?!\.filter)/',
            "...refreshPaths.filter((refreshPath) => refreshPath !== 'resources/views/**')",
            $paths
        );

        // refreshPaths was what reloaded Blade views: globs resolve from the Vite root (the
        // theme or plugin), so a project-relative themes/{name}/resources/views/** never matched
        if (! preg_match('/[\'"]'.preg_quote(self::BLADE_REFRESH_PATH, '/').'[\'"]/', $paths)) {
            $trailingComma = str_ends_with($paths, ',') ? ',' : '';
            $paths = rtrim($paths, ", \n\t");
            $lastLine = (string) strrchr("\n".$paths, "\n");
            $separator = str_contains($paths, "\n")
                ? ",\n".substr($lastLine, 1, strspn($lastLine, " \t", 1))
                : ', ';
            $paths .= $separator."'".self::BLADE_REFRESH_PATH."'".$trailingComma;
        }

        return substr_replace($content, $paths.$trailingWhitespace, $matches[1][1], strlen($original));
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
        foreach (explode("\n", $this->blockEntriesCode(includeLegacy: false)) as $line) {
            $this->line('  '.$line);
        }

        $this->newLine();
        $this->line('  // In input: [..., ...Object.values(blockEntries)]');
        $this->line('  // In plugins: [...(Object.keys(blockEntries).length > 0 ? [wordpressPlugin()] : [])]');
        $this->line(sprintf("  // In refresh: reload for '%s' only, not resources/views/**", self::BLADE_REFRESH_PATH));
        $this->newLine();
    }

    /**
     * Add npm dependencies to package.json.
     */
    private function addNpmDependencies(array $target): void
    {
        $packageJsonPath = $target['path'].'/package.json';

        if (! file_exists($packageJsonPath)) {
            $this->components->error(sprintf('package.json not found in %s. Run npm init -y first.', $target['path']));

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
        $isDynamic = ! $this->option('static');
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
            $blockJsonData['render'] = 'file:./render.blade.php';
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

        // render.blade.php (only for dynamic blocks)
        if ($isDynamic) {
            // The title lands in a single-quoted PHP string
            $this->writeStub($blockDir.'/render.blade.php', $this->getStubContent('render.blade.php'), [
                ...$replacements,
                '{{ title }}' => addcslashes($title, "'\\"),
            ]);
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
        $publishedPath = base_path(sprintf('stubs/pollora-block/%s.stub', $stubName));

        if (file_exists($publishedPath)) {
            return file_get_contents($publishedPath);
        }

        return file_get_contents(dirname(__DIR__, 2).sprintf('/stubs/%s.stub', $stubName));
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
            ['static', null, InputOption::VALUE_NONE, 'Create a static block saved in post content (save.jsx) instead of rendered with Blade'],
            ['dynamic', null, InputOption::VALUE_NONE, 'Deprecated: blocks are dynamic by default'],
            ['inner-blocks', null, InputOption::VALUE_NONE, 'Add InnerBlocks support'],
            ['no-view-script', null, InputOption::VALUE_NONE, 'Do not generate a frontend view script'],
            ['force', null, InputOption::VALUE_NONE, 'Overwrite existing block without confirmation'],
        ];
    }
}
