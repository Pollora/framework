<?php

declare(strict_types=1);

namespace Pollora\Block\Infrastructure\Services;

use Illuminate\Support\Facades\Log;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Domain\Contracts\ViteManagerInterface;
use Pollora\Asset\Infrastructure\Services\ViteManager;
use Pollora\Block\Domain\Contracts\BlockRegistrarInterface;
use Pollora\Hook\Infrastructure\Services\Filter as HookFilter;

/**
 * Scans block directories and registers Gutenberg blocks using the Asset system.
 *
 * For each block directory containing a block.json, this service:
 * 1. Reads the block metadata
 * 2. Creates a dedicated `{parent}.blocks` container (no basePath) for Vite resolution
 * 3. Pre-registers script/style handles via wp_register_script/style with Vite-resolved URLs
 * 4. Calls register_block_type() — WP finds the pre-registered handles and skips its own resolution
 */
class BlockRegistrar implements BlockRegistrarInterface
{
    /**
     * Script fields in block.json.
     */
    private const SCRIPT_FIELDS = ['editorScript', 'script', 'viewScript'];

    /**
     * Style fields in block.json.
     */
    private const STYLE_FIELDS = ['editorStyle', 'style', 'viewStyle'];

    /**
     * Default WordPress dependencies for editor scripts.
     */
    private const DEFAULT_EDITOR_DEPS = ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n'];

    public function __construct(
        private readonly AssetManager $assetManager,
    ) {}

    public function registerDirectory(string $directory, string $containerName): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \DirectoryIterator($directory);

        foreach ($iterator as $item) {
            if ($item->isDot() || ! $item->isDir()) {
                continue;
            }

            $blockDir = $item->getPathname();

            if (file_exists($blockDir.'/block.json')) {
                $this->registerBlock($blockDir, $containerName);
            }
        }
    }

    public function registerBlock(string $blockDir, string $containerName): void
    {
        $metadataFile = $blockDir.'/block.json';

        if (! file_exists($metadataFile)) {
            Log::warning("BlockRegistrar: block.json not found in {$blockDir}");

            return;
        }

        $metadata = json_decode(file_get_contents($metadataFile), true);

        if (! is_array($metadata) || ! isset($metadata['name'])) {
            Log::warning("BlockRegistrar: Invalid block.json in {$blockDir}");

            return;
        }

        $viteManager = $this->getBlocksViteManager($containerName);

        if ($viteManager === null) {
            return;
        }

        $slug = basename($blockDir);
        $blockName = $metadata['name'];

        // Pre-register all asset handles BEFORE register_block_type().
        // WP's register_block_script_handle() checks wp_script_is($handle, 'registered')
        // and short-circuits when it finds our pre-registered handles.
        foreach (self::SCRIPT_FIELDS as $field) {
            $this->registerScriptHandle($metadata, $field, $slug, $blockName, $viteManager);
        }

        foreach (self::STYLE_FIELDS as $field) {
            $this->registerStyleHandle($metadata, $field, $slug, $blockName, $viteManager);
        }

        // Let WordPress handle block registration natively.
        // It reads block.json, generates handles, finds them already registered, and wires everything.
        $args = [];

        if (isset($metadata['render']) && str_starts_with($metadata['render'], 'file:./')) {
            $renderFile = $blockDir.'/'.substr($metadata['render'], 7);

            if (file_exists($renderFile)) {
                $args['render_callback'] = function (array $attributes, string $content, \WP_Block $block) use ($renderFile): string {
                    ob_start();
                    // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
                    extract([
                        'attributes' => $attributes,
                        'content' => $content,
                        'block' => $block,
                    ]);
                    include $renderFile;

                    return ob_get_clean();
                };
            }
        }

        register_block_type($blockDir, $args);
    }

    /**
     * Pre-register a script handle with the Vite-resolved URL.
     */
    private function registerScriptHandle(
        array $metadata,
        string $field,
        string $slug,
        string $blockName,
        ViteManagerInterface $viteManager,
    ): void {
        if (! isset($metadata[$field]) || ! str_starts_with($metadata[$field], 'file:./')) {
            return;
        }

        $relativeFile = substr($metadata[$field], 7);
        $entryPoint = "resources/blocks/{$slug}/{$relativeFile}";
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
     */
    private function registerStyleHandle(
        array $metadata,
        string $field,
        string $slug,
        string $blockName,
        ViteManagerInterface $viteManager,
    ): void {
        if (! isset($metadata[$field]) || ! str_starts_with($metadata[$field], 'file:./')) {
            return;
        }

        $relativeFile = substr($metadata[$field], 7);
        $entryPoint = "resources/blocks/{$slug}/{$relativeFile}";
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
     * Add type="module" and crossorigin attributes for Vite scripts.
     */
    private function addModuleTypeAttribute(string $handle): void
    {
        resolve(HookFilter::class)->add('script_loader_tag', function (string $tag, string $tagHandle) use ($handle): string {
            if ($tagHandle !== $handle) {
                return $tag;
            }

            if (! str_contains($tag, 'type="module"')) {
                $tag = str_replace(' src=', ' type="module" crossorigin src=', $tag);
            }

            return $tag;
        }, 10, 2);
    }

    /**
     * Get or create a ViteManager for the blocks container.
     *
     * Creates a `{parent}.blocks` container with empty basePath so that block entry points
     * like `resources/blocks/hero/index.jsx` resolve directly against the Vite manifest.
     */
    protected function getBlocksViteManager(string $parentContainerName): ?ViteManagerInterface
    {
        $blocksContainerName = $parentContainerName.'.blocks';

        if ($this->assetManager->getContainer($blocksContainerName) === null) {
            $parentContainer = $this->assetManager->getContainer($parentContainerName);

            if ($parentContainer === null) {
                Log::warning("BlockRegistrar: Asset container '{$parentContainerName}' not found");

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
        $suffix = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $field));

        return "{$base}-{$suffix}";
    }
}
