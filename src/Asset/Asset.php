<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Log;
use Pollora\Support\Facades\Action;
use Pollora\Support\Facades\Filter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Handles the registration and enqueuing of CSS and JavaScript assets in WordPress.
 *
 * This class provides a fluent interface for managing assets, supporting both traditional
 * WordPress enqueuing and Vite.js integration. It handles various asset loading scenarios
 * including dependencies, versioning, and conditional loading.
 */
class Asset
{
    /**
     * The path or array of paths to the asset file(s).
     *
     * @var string|array<string>
     */
    protected string|array $path;

    /**
     * The type of asset ('css' or 'js').
     */
    protected string $type;

    /**
     * Array of asset handles that this asset depends on.
     *
     * @var array<string>
     */
    protected array $dependencies = [];

    /**
     * The Vite instance for asset compilation.
     */
    protected ?Vite $vite = null;

    /**
     * Whether to use Vite.js for asset handling.
     */
    protected bool $useVite = false;

    /**
     * The version string for cache busting.
     */
    protected ?string $version = null;

    /**
     * The media query for stylesheet (e.g., 'all', 'print', 'screen').
     */
    protected string $media = 'all';

    /**
     * Whether to load the script in the footer.
     */
    protected bool $loadInFooter = false;

    /**
     * The loading strategy for scripts (e.g., 'defer', 'async').
     */
    protected ?string $loadStrategy = null;

    /**
     * The content to be added inline with the asset.
     */
    protected ?string $inlineContent = null;

    /**
     * The position for inline content ('before' or 'after').
     */
    protected ?string $inlinePosition = null;

    /**
     * Array of WordPress hooks where the asset should be enqueued.
     *
     * @var array<string>
     */
    protected array $hooks = [];

    /**
     * The asset container instance.
     */
    protected ?AssetContainer $container = null;

    /**
     * The Vite manager instance.
     */
    protected ?ViteManager $viteManager = null;

    /**
     * Creates a new asset instance.
     *
     * @param  string  $handle  Unique identifier for the asset
     * @param  string  $path  Path to the asset file
     */
    public function __construct(protected string $handle, string $path)
    {
        $this->path = str_replace(base_path('/'), '', $path);
        $this->type = $this->determineFileType($path);
        $this->container = app(AssetContainerManager::class)->getDefault();
    }

    /**
     * Sets the asset container.
     *
     * @param  string  $containerName  Name of the container
     */
    public function container(string $containerName): self
    {
        try {
            $this->container = app(AssetContainerManager::class)->get($containerName);

            if ($this->useVite) {
                $this->vite->setContainer($this->container);
                $this->vite->setClient($this->path);
            }
        } catch (NotFoundExceptionInterface $e) {
            Log::error("Asset container '{$containerName}' not found.");

            return $this;

        } catch (ContainerExceptionInterface $e) {
            Log::error("Container exception: {$e->getMessage()}");

            return $this;

        }

        return $this;
    }

    /**
     * Sets asset dependencies.
     *
     * @param  array  $dependencies  Array of dependency handles
     */
    public function dependencies(array $dependencies): self
    {
        $this->dependencies = $dependencies;

        return $this;
    }

    /**
     * Enables Vite.js integration for this asset.
     */
    public function useVite(): self
    {
        $this->useVite = true;
        $this->viteManager = app(ViteManager::class);

        return $this;
    }

    /**
     * Sets the asset version.
     *
     * @param  string  $version  Version string
     */
    public function version(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Sets the media type for stylesheets.
     *
     * @param  string  $media  Media query string
     */
    public function media(string $media): self
    {
        $this->media = $media;

        return $this;
    }

    /**
     * Sets script to load in footer.
     */
    public function loadInFooter(): self
    {
        $this->loadInFooter = true;

        return $this;
    }

    /**
     * Sets the loading strategy for scripts.
     *
     * @param  string  $strategy  Loading strategy (e.g., 'defer', 'async')
     */
    public function loadStrategy(string $strategy): self
    {
        $this->loadStrategy = $strategy;

        return $this;
    }

    /**
     * Sets the asset type manually.
     *
     * @param  string  $type  The asset type ('css' or 'js')
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Enqueues the asset in WordPress frontend.
     *
     * Registers the asset to be loaded on the public-facing side of the site
     * using the 'wp_enqueue_scripts' hook.
     *
     * @return self Returns the current instance for method chaining
     */
    public function toFrontend(): self
    {
        return $this->addHook('wp_enqueue_scripts');
    }

    /**
     * Enqueues the asset in WordPress admin area.
     *
     * Registers the asset to be loaded in the WordPress administration panel
     * using the 'admin_enqueue_scripts' hook.
     *
     * @return self Returns the current instance for method chaining
     */
    public function toBackend(): self
    {
        return $this->addHook('admin_enqueue_scripts');
    }

    /**
     * Enqueues the asset on the login screen.
     *
     * Registers the asset to be loaded on the WordPress login page
     * using the 'login_enqueue_scripts' hook.
     *
     * @return self Returns the current instance for method chaining
     */
    public function toLoginScreen(): self
    {
        return $this->addHook('login_enqueue_scripts');
    }

    /**
     * Enqueues the asset in the customizer preview.
     *
     * Registers the asset to be loaded in the WordPress theme customizer preview
     * using the 'customize_preview_init' hook.
     *
     * @return self Returns the current instance for method chaining
     */
    public function toCustomizer(): self
    {
        return $this->addHook('customize_preview_init');
    }

    /**
     * Enqueues the asset in the block editor.
     *
     * Registers the asset to be loaded in the Gutenberg block editor
     * using the 'enqueue_block_editor_assets' hook.
     *
     * @return self Returns the current instance for method chaining
     */
    public function toEditor(): self
    {
        return $this->addHook('enqueue_block_editor_assets');
    }

    /**
     * Localizes a JavaScript file with data.
     *
     * @param  string  $objectName  JavaScript object name
     * @param  array  $data  Data to localize
     */
    public function localize(string $objectName, array $data): self
    {
        if ($this->type === 'script') {
            wp_localize_script($this->handle, $objectName, $data);
        }

        return $this;
    }

    /**
     * Adds inline content to the asset.
     *
     * @param  string  $content  Inline CSS/JS content
     * @param  string  $position  Position ('before' or 'after')
     */
    public function inline(string $content, string $position = 'after'): self
    {
        $this->inlineContent = $content;
        $this->inlinePosition = $position;

        return $this;
    }

    /**
     * Handles the actual enqueuing of the asset.
     * Called automatically when the object is destroyed.
     */
    public function __destruct()
    {
        try {
            $this->hooks = $this->hooks !== [] ? $this->hooks : ['wp_enqueue_scripts'];

            if ($this->useVite) {
                $this->configureViteAssets();
            }

            foreach ($this->hooks as $hook) {
                if ($this->needToLoadViteClient()) {
                    $this->loadViteClient($hook);
                }
                Action::add($hook, $this->enqueueStyleOrScript(...), 99);
            }
        } catch (\Throwable $e) {
            Log::error('Error in Asset destructor', [
                'error' => $e->getMessage(),
                'hooks' => $this->hooks,
                'path' => $this->path ?? null,
            ]);
        }
    }

    /**
     * Enqueues all styles and scripts for the current asset.
     */
    public function enqueueStyleOrScript(): void
    {
        $paths = $this->getAssetPaths();

        foreach ($paths as $type => $pathList) {
            foreach ($pathList as $path) {
                $this->enqueueAsset((string) $type, $this->forceFullUrl($path));
            }
        }
    }

    /**
     * Adds a WordPress hook for asset enqueuing.
     *
     * @param  string  $hook  WordPress hook name
     */
    protected function addHook(string $hook): self
    {
        $this->hooks[] = $hook;

        return $this;
    }

    /**
     * Loads the Vite client script when in development mode.
     *
     * @param  string  $hook  WordPress hook to attach the client script
     */
    protected function loadViteClient(string $hook): void
    {
        Action::add($hook, function (): void {
            if ($this->viteManager && $this->viteManager->isRunningHot()) {
                echo $this->viteManager->getViteClientHtml();
            }
        }, 1);
    }

    /**
     * Configures asset paths for Vite integration.
     *
     * @throws \RuntimeException When Vite manager is not initialized
     */
    public function configureViteAssets(): void
    {
        if (! $this->viteManager instanceof \Pollora\Asset\ViteManager) {
            throw new \RuntimeException('Vite manager not initialized. Call useVite() first.');
        }

        $asset = $this->viteManager->isRunningHot()
            ? $this->viteManager->asset($this->path)
            : $this->viteManager->getAssetUrls([$this->path]);
        $this->path = $asset;
    }

    /**
     * Determines if the Vite client needs to be loaded.
     *
     * @return bool True if Vite client should be loaded
     */
    protected function needToLoadViteClient(): bool
    {
        return $this->useVite && $this->viteManager && $this->viteManager->isRunningHot();
    }

    /**
     * Gets the processed asset paths based on Vite configuration.
     *
     * @return array Array of asset paths grouped by type
     */
    protected function getAssetPaths(): array
    {
        if ($this->useVite && ! $this->viteManager->isRunningHot()) {
            return $this->path;
        }

        return [$this->type => [$this->path]];
    }

    /**
     * Enqueues an individual asset based on its type.
     *
     * @param  string  $type  Asset type (css or js)
     * @param  string  $path  Asset path
     *
     * @throws \InvalidArgumentException When asset type is not supported
     */
    protected function enqueueAsset(string $type, string $path): void
    {
        $handle = $this->useVite && ! $this->viteManager->isRunningHot() ? $this->handle.'/'.sanitize_title(basename($path)) : $this->handle;
        match ($type) {
            'css' => $this->enqueueStyle($path, $handle),
            'js' => $this->enqueueScript($path),
            default => throw new \InvalidArgumentException("Unsupported asset type: {$type}")
        };
    }

    /**
     * Enqueues a JavaScript file with WordPress.
     *
     * @param  string  $path  Path to the JavaScript file
     */
    protected function enqueueScript(string $path): void
    {
        wp_enqueue_script($this->handle, $path, $this->dependencies, $this->version, $this->loadInFooter);

        if ($this->useVite) {
            $this->addViteScriptAttributes();
        }

        if ($this->loadStrategy !== null && $this->loadStrategy !== '' && $this->loadStrategy !== '0') {
            wp_script_add_data($this->handle, 'defer', true);
        }

        if ($this->inlineContent !== null && $this->inlineContent !== '' && $this->inlineContent !== '0') {
            wp_add_inline_script($this->handle, $this->inlineContent, $this->inlinePosition);
        }
    }

    /**
     * Enqueues a CSS file with WordPress.
     *
     * @param  string  $path  Path to the CSS file
     * @param  string  $handle  Unique identifier for the stylesheet
     */
    protected function enqueueStyle(string $path, string $handle): void
    {
        wp_enqueue_style($handle, $path, $this->dependencies, $this->version, $this->media);

        if ($this->inlineContent !== null && $this->inlineContent !== '' && $this->inlineContent !== '0') {
            wp_add_inline_style($handle, $this->inlineContent);
        }
    }

    /**
     * Adds module and crossorigin attributes to Vite script tags.
     */
    protected function addViteScriptAttributes(): void
    {
        Filter::add('script_loader_tag', fn ($tag, $handle, $src) => $handle === $this->handle
            ? '<script type="module" crossorigin src="'.esc_url($src).'"></script>'
            : $tag, 10, 3);
    }

    /**
     * Ensures a full URL is used for the asset path.
     *
     * @param  string  $path  Asset path
     * @return string Full URL to the asset
     */
    protected function forceFullUrl(string $path): string
    {
        if (str_contains($path, '://')) {
            return $path;
        }

        $basePath = $this->container->getBasePath();
        $fullPath = $basePath.'/'.ltrim($path, '/');

        return home_url($fullPath);
    }

    /**
     * Determines the file type from the path extension.
     *
     * @param  string  $path  File path
     * @return string File type (css or js)
     *
     * @throws \InvalidArgumentException When file type is not supported
     */
    protected function determineFileType(string $path): string
    {
        $type = pathinfo($path, PATHINFO_EXTENSION);

        if (! in_array($type, ['css', 'js'])) {
            throw new \InvalidArgumentException("Unsupported file type: {$type}");
        }

        return $type;
    }
}
