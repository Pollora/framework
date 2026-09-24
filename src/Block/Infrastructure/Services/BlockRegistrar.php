<?php

declare(strict_types=1);

namespace Pollora\Block\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Log;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Domain\Contracts\ViteManagerInterface;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;
use Pollora\Asset\Infrastructure\Services\ViteManager;
use Pollora\Block\Domain\Contracts\BlockRegistrarInterface;
use Pollora\Hook\Domain\Contract\Filter as HookFilter;

/**
 * Scans block directories and registers Gutenberg blocks using the Asset system.
 *
 * For each block directory containing a block.json, this service:
 * 1. Reads the block metadata
 * 2. Creates a dedicated `{parent}.blocks` container (no basePath) for Vite resolution
 * 3. Pre-registers script/style handles via wp_register_script/style with Vite-resolved URLs
 * 4. Calls register_block_type() — WP finds the pre-registered handles and skips its own resolution
 *
 * Blocks live in `resources/views/blocks/{slug}`. The former `resources/blocks` directory
 * is still scanned, with a deprecation notice, until v15.
 */
class BlockRegistrar implements BlockRegistrarInterface
{
    /**
     * Script fields in block.json.
     */
    private const array SCRIPT_FIELDS = ['editorScript', 'script', 'viewScript'];

    /**
     * Style fields in block.json.
     */
    private const array STYLE_FIELDS = ['editorStyle', 'style', 'viewStyle'];

    /**
     * Default WordPress dependencies for editor scripts.
     */
    private const array DEFAULT_EDITOR_DEPS = ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n'];

    /**
     * Blocks directory, relative to the theme or plugin root.
     */
    private const string BLOCKS_DIRECTORY = 'resources/views/blocks';

    /**
     * Blocks directory used before v13.32, relative to the theme or plugin root.
     */
    private const string LEGACY_BLOCKS_DIRECTORY = 'resources/blocks';

    /**
     * Files marking the root of a Vite project.
     */
    private const array VITE_CONFIG_FILES = ['vite.config.js', 'vite.config.ts', 'vite.config.mjs', 'vite.config.mts', 'vite.config.cjs', 'vite.config.cts'];

    /**
     * Legacy directories already reported as deprecated during this request.
     *
     * @var array<string, true>
     */
    private static array $reportedLegacyDirectories = [];

    /**
     * Vite project roots already detected, keyed by block directory.
     *
     * @var array<string, string|null>
     */
    private array $viteRoots = [];

    public function __construct(
        private readonly AssetManager $assetManager,
        private readonly HookFilter $filter,
    ) {}

    public function registerDirectory(string $directory, string $containerName, ?string $basePath = null): void
    {
        if (! function_exists('register_block_type')) {
            return;
        }

        $registered = [];

        foreach ($this->resolveBlocksDirectories($directory) as $blocksDirectory => $isLegacy) {
            foreach ($this->findBlockDirectories($blocksDirectory) as $blockDir => $blockName) {
                if (isset($registered[$blockName])) {
                    Log::warning(sprintf(
                        'BlockRegistrar: block "%s" exists in both %s and %s; the one in %s is used.',
                        $blockName,
                        $registered[$blockName],
                        $blockDir,
                        $registered[$blockName],
                    ));

                    continue;
                }

                if ($isLegacy) {
                    $this->reportLegacyDirectory($blocksDirectory);
                }

                $registered[$blockName] = $blockDir;
                $this->registerBlock($blockDir, $containerName, $basePath);
            }
        }
    }

    public function registerBlock(string $blockDir, string $containerName, ?string $basePath = null): void
    {
        $metadataFile = $blockDir.'/block.json';

        if (! file_exists($metadataFile)) {
            Log::warning('BlockRegistrar: block.json not found in '.$blockDir);

            return;
        }

        $metadata = json_decode((string) file_get_contents($metadataFile), true);

        if (! is_array($metadata) || ! isset($metadata['name'])) {
            Log::warning('BlockRegistrar: Invalid block.json in '.$blockDir);

            return;
        }

        $blockName = $metadata['name'];

        // The framework registers every module's blocks by convention; a
        // BlocksServiceProvider kept from an earlier release registers them
        // again, and WordPress would reject the duplicate with a notice.
        if ($this->isBlockRegistered($blockName)) {
            return;
        }

        $viteManager = $this->getBlocksViteManager($containerName);

        if (! $viteManager instanceof ViteManagerInterface) {
            return;
        }

        // Pre-register all asset handles BEFORE register_block_type().
        // WP's register_block_script_handle() checks wp_script_is($handle, 'registered')
        // and short-circuits when it finds our pre-registered handles.
        foreach (self::SCRIPT_FIELDS as $field) {
            $this->registerScriptHandle($metadata, $field, $blockDir, $basePath, $blockName, $viteManager);
        }

        foreach (self::STYLE_FIELDS as $field) {
            $this->registerStyleHandle($metadata, $field, $blockDir, $basePath, $blockName, $viteManager);
        }

        // Let WordPress handle block registration natively.
        // It reads block.json, generates handles, finds them already registered, and wires everything.
        $args = [];

        if (isset($metadata['render']) && is_string($metadata['render'])) {
            // Always set: $args override the render_callback WordPress builds from block.json,
            // which would print a Blade template raw or include a file outside the block
            $args['render_callback'] = $this->buildRenderCallback($blockDir, $metadata['render']);
        }

        register_block_type($blockDir, $args);
    }

    /**
     * Whether WordPress already holds a block type with this name.
     */
    protected function isBlockRegistered(string $blockName): bool
    {
        return class_exists(\WP_Block_Type_Registry::class)
            && \WP_Block_Type_Registry::get_instance()->is_registered($blockName);
    }

    /**
     * Map the directories to scan to whether they are the deprecated location.
     *
     * A theme or plugin blocks directory — new or legacy — scans both locations,
     * the new one first so it wins over a block with the same name.
     *
     * @return array<string, bool>
     */
    private function resolveBlocksDirectories(string $directory): array
    {
        $normalized = rtrim(str_replace('\\', '/', $directory), '/');
        $root = match (true) {
            str_ends_with($normalized, '/'.self::BLOCKS_DIRECTORY) => substr($normalized, 0, -strlen('/'.self::BLOCKS_DIRECTORY)),
            str_ends_with($normalized, '/'.self::LEGACY_BLOCKS_DIRECTORY) => substr($normalized, 0, -strlen('/'.self::LEGACY_BLOCKS_DIRECTORY)),
            default => null,
        };

        if ($root === null) {
            return is_dir($directory) ? [$directory => false] : [];
        }

        return array_filter([
            $root.'/'.self::BLOCKS_DIRECTORY => false,
            $root.'/'.self::LEGACY_BLOCKS_DIRECTORY => true,
        ], is_dir(...), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Find the block subdirectories of a directory, sorted by name.
     *
     * @return array<string, string> Block directory => block name from block.json
     */
    private function findBlockDirectories(string $directory): array
    {
        $blocks = [];

        foreach (new \DirectoryIterator($directory) as $item) {
            if ($item->isDot() || ! $item->isDir()) {
                continue;
            }

            $metadataFile = $item->getPathname().'/block.json';

            if (! file_exists($metadataFile)) {
                continue;
            }

            $metadata = json_decode((string) file_get_contents($metadataFile), true);

            // An invalid block.json is reported by registerBlock()
            $blocks[$item->getPathname()] = is_array($metadata) && is_string($metadata['name'] ?? null)
                ? $metadata['name']
                : $item->getPathname();
        }

        ksort($blocks);

        return $blocks;
    }

    /**
     * Log, once per request, that a theme or plugin still uses resources/blocks.
     */
    private function reportLegacyDirectory(string $directory): void
    {
        if (isset(self::$reportedLegacyDirectories[$directory])) {
            return;
        }

        self::$reportedLegacyDirectories[$directory] = true;

        Log::notice(sprintf(
            'BlockRegistrar: blocks in %s are deprecated and will no longer be registered in Pollora v15. Move them to %s.',
            $directory,
            dirname($directory).'/views/blocks',
        ));
    }

    /**
     * Build the render callback for a block.json "render" file.
     *
     * Returns null when the file is missing or outside the block directory, so that
     * WordPress does not include it either.
     */
    private function buildRenderCallback(string $blockDir, string $render): ?\Closure
    {
        $relativeFile = $this->stripFilePrefix($render);
        $realRenderFile = realpath($blockDir.'/'.$relativeFile);
        $realBlockDir = realpath($blockDir);

        if ($realRenderFile === false || $realBlockDir === false || ! str_starts_with($realRenderFile, $realBlockDir.DIRECTORY_SEPARATOR)) {
            Log::warning(sprintf('BlockRegistrar: render file "%s" not found in %s', $render, $blockDir));

            return null;
        }

        if (str_ends_with($realRenderFile, '.blade.php')) {
            return static fn (array $attributes, string $content, \WP_Block $block): string => Container::getInstance()
                ->make(ViewFactory::class)
                ->file($realRenderFile, [
                    'attributes' => $attributes,
                    'content' => $content,
                    'block' => $block,
                ])
                ->render();
        }

        return static function (array $attributes, string $content, \WP_Block $block) use ($realRenderFile): string {
            ob_start();
            include $realRenderFile;

            return (string) ob_get_clean();
        };
    }

    /**
     * Pre-register a script handle with the Vite-resolved URL.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function registerScriptHandle(
        array $metadata,
        string $field,
        string $blockDir,
        ?string $basePath,
        string $blockName,
        ViteManagerInterface $viteManager,
    ): void {
        $entryPoint = $this->resolveAssetEntryPoint($metadata, $field, $blockDir, $basePath);

        if ($entryPoint === null) {
            return;
        }

        $handle = $this->buildHandle($blockName, $field);
        $deps = $field === 'editorScript' ? self::DEFAULT_EDITOR_DEPS : [];

        if ($viteManager->isRunningHot()) {
            wp_register_script($handle, $viteManager->asset($entryPoint), $deps, null, true);
            $this->addModuleTypeAttribute($handle);
        } else {
            $urls = $viteManager->getAssetUrls([$entryPoint]);

            if (! empty($urls['js'])) {
                wp_register_script($handle, $urls['js'][0], $deps, null, true);
                $this->addModuleTypeAttribute($handle);
            }

            // Register extracted CSS from JS entry (Vite code-splits CSS)
            if (! empty($urls['css'])) {
                foreach ($urls['css'] as $cssUrl) {
                    wp_register_style($handle.'-style', $cssUrl, [], null);
                }
            }
        }
    }

    /**
     * Pre-register a style handle with the Vite-resolved URL.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function registerStyleHandle(
        array $metadata,
        string $field,
        string $blockDir,
        ?string $basePath,
        string $blockName,
        ViteManagerInterface $viteManager,
    ): void {
        $entryPoint = $this->resolveAssetEntryPoint($metadata, $field, $blockDir, $basePath);

        if ($entryPoint === null) {
            return;
        }

        $handle = $this->buildHandle($blockName, $field);

        if ($viteManager->isRunningHot()) {
            wp_register_style($handle, $viteManager->asset($entryPoint), [], null);
        } else {
            $urls = $viteManager->getAssetUrls([$entryPoint]);

            if (! empty($urls['css'])) {
                wp_register_style($handle, $urls['css'][0], [], null);
            }
        }
    }

    /**
     * Resolve the Vite entry point of a block.json asset field.
     *
     * The entry point is the asset path relative to the Vite project root, which is
     * both the Vite manifest key and the dev server path
     * (e.g. `resources/views/blocks/hero/index.jsx`).
     *
     * @param  array<string, mixed>  $metadata
     */
    private function resolveAssetEntryPoint(array $metadata, string $field, string $blockDir, ?string $basePath): ?string
    {
        if (! isset($metadata[$field]) || ! is_string($metadata[$field]) || ! str_starts_with($metadata[$field], 'file:')) {
            return null;
        }

        $root = $basePath ?? $this->detectViteRoot($blockDir);

        if ($root === null) {
            Log::warning(sprintf('BlockRegistrar: no Vite project root found for %s; pass basePath to registerDirectory()', $blockDir));

            return null;
        }

        $root = $this->normalizePath(realpath($root) ?: $root);
        $file = $this->normalizePath((realpath($blockDir) ?: $blockDir).'/'.$this->stripFilePrefix($metadata[$field]));

        if (! str_starts_with($file, $root.'/')) {
            Log::warning(sprintf('BlockRegistrar: %s of %s is outside the Vite project root %s', $field, $blockDir, $root));

            return null;
        }

        return substr($file, strlen($root) + 1);
    }

    /**
     * Find the Vite project root of a block: the closest parent holding a Vite config,
     * or else the directory holding its `resources` folder.
     */
    private function detectViteRoot(string $blockDir): ?string
    {
        if (array_key_exists($blockDir, $this->viteRoots)) {
            return $this->viteRoots[$blockDir];
        }

        $directory = $this->normalizePath(realpath($blockDir) ?: $blockDir);
        $root = null;

        for ($current = $directory; $current !== dirname($current); $current = dirname($current)) {
            foreach (self::VITE_CONFIG_FILES as $configFile) {
                if (file_exists($current.'/'.$configFile)) {
                    $root = $current;

                    break 2;
                }
            }
        }

        // Deployments do not always ship vite.config.js next to the built assets
        if ($root === null && ($position = strrpos($directory, '/resources/')) !== false) {
            $root = substr($directory, 0, $position);
        }

        return $this->viteRoots[$blockDir] = $root;
    }

    /**
     * Remove the "file:" prefix and leading "./" from a block.json file reference.
     */
    private function stripFilePrefix(string $reference): string
    {
        $path = str_starts_with($reference, 'file:') ? substr($reference, 5) : $reference;

        return str_starts_with($path, './') ? substr($path, 2) : $path;
    }

    /**
     * Normalize a path to forward slashes, resolving "." and ".." segments lexically.
     */
    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $segments = [];

        foreach (explode('/', $path) as $index => $segment) {
            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            if ($segment !== '.' && ($segment !== '' || $index === 0)) {
                $segments[] = $segment;
            }
        }

        return implode('/', $segments);
    }

    /**
     * Add type="module" and crossorigin attributes for Vite scripts.
     */
    private function addModuleTypeAttribute(string $handle): void
    {
        $this->filter->add('script_loader_tag', function (string $tag, string $tagHandle) use ($handle): string {
            if ($tagHandle !== $handle) {
                return $tag;
            }

            if (! str_contains($tag, 'type="module"')) {
                return str_replace(' src=', ' type="module" crossorigin src=', $tag);
            }

            return $tag;
        }, 10, 2);
    }

    /**
     * Get or create a ViteManager for the blocks container.
     *
     * Creates a `{parent}.blocks` container with empty basePath so that block entry points
     * like `resources/views/blocks/hero/index.jsx` resolve directly against the Vite manifest.
     */
    protected function getBlocksViteManager(string $parentContainerName): ?ViteManagerInterface
    {
        $blocksContainerName = $parentContainerName.'.blocks';

        if (! $this->assetManager->getContainer($blocksContainerName) instanceof AssetContainer) {
            $parentContainer = $this->assetManager->getContainer($parentContainerName);

            if (! $parentContainer instanceof AssetContainer) {
                Log::warning(sprintf("BlockRegistrar: Asset container '%s' not found", $parentContainerName));

                return null;
            }

            $this->assetManager->addContainer($blocksContainerName, [
                'hot_file' => $parentContainer->getHotFile(),
                'build_directory' => $parentContainer->getBuildDirectory(),
                'manifest_path' => $parentContainer->getManifestPath(),
                'base_path' => '',
            ]);
        }

        return new ViteManager($this->assetManager->getContainer($blocksContainerName));
    }

    /**
     * Build a WordPress handle from block name and field.
     *
     * Uses the same format as WordPress's generate_block_asset_handle()
     * so that register_block_type() finds our pre-registered handles.
     *
     * Example: "acme/hero-banner" + "editorScript" => "acme-hero-banner-editor-script"
     */
    private function buildHandle(string $blockName, string $field): string
    {
        $base = str_replace('/', '-', $blockName);
        $suffix = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', $field));

        return sprintf('%s-%s', $base, $suffix);
    }
}
