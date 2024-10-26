<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Illuminate\Support\Facades\Vite;
use Pollora\Support\Facades\Action;
use Pollora\Support\Facades\Filter;

class Asset
{
    protected string|array $path;

    protected string $type;

    protected array $dependencies = [];

    protected ?\Illuminate\Foundation\Vite $vite = null;

    protected bool $useVite = false;

    protected ?string $version = null;

    protected string $media = 'all';

    protected bool $loadInFooter = false;

    protected ?string $loadStrategy = null;

    protected ?string $inlineContent = null;

    protected ?string $inlinePosition = null;

    protected array $hooks = [];

    protected ?AssetContainer $container = null;

    protected ?ViteManager $viteManager = null;

    public function __construct(protected string $handle, string $path)
    {
        $this->path = str_replace(base_path('/'), '', $path);
        $this->type = $this->determineFileType($path);
        $this->container = app('asset.container')->getDefault();
    }

    public function container(string $containerName): self
    {
        $this->container = app('asset.container')->get($containerName);
        if ($this->useVite) {
            $this->vite->setContainer($this->container);
            $this->vite->setClient($this->path);
        }

        return $this;
    }

    public function dependencies(array $dependencies): self
    {
        $this->dependencies = $dependencies;

        return $this;
    }

    public function useVite(): self
    {
        $this->useVite = true;
        $this->viteManager = app(ViteManager::class);

        return $this;
    }

    public function version(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function media(string $media): self
    {
        $this->media = $media;

        return $this;
    }

    public function loadInFooter(): self
    {
        $this->loadInFooter = true;

        return $this;
    }

    public function loadStrategy(string $strategy): self
    {
        $this->loadStrategy = $strategy;

        return $this;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function toFrontend(): self
    {
        return $this->addHook('wp_enqueue_scripts');
    }

    public function toBackend(): self
    {
        return $this->addHook('admin_enqueue_scripts');
    }

    public function toLoginScreen(): self
    {
        return $this->addHook('login_enqueue_scripts');
    }

    public function toCustomizer(): self
    {
        return $this->addHook('customize_preview_init');
    }

    public function toEditor(): self
    {
        return $this->addHook('enqueue_block_editor_assets');
    }

    public function localize(string $objectName, array $data): self
    {
        if ($this->type === 'script') {
            wp_localize_script($this->handle, $objectName, $data);
        }

        return $this;
    }

    public function inline(string $content, string $position = 'after'): self
    {
        $this->inlineContent = $content;
        $this->inlinePosition = $position;

        return $this;
    }

    public function __destruct()
    {
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
    }

    public function enqueueStyleOrScript(): void
    {
        $paths = $this->getAssetPaths();

        foreach ($paths as $type => $pathList) {
            foreach ($pathList as $path) {
                $this->enqueueAsset($type, $this->forceFullUrl($path));
            }
        }
    }

    protected function addHook(string $hook): self
    {
        $this->hooks[] = $hook;

        return $this;
    }

    protected function loadViteClient(string $hook): void
    {
        Action::add($hook, function (): void {
            if ($this->viteManager && $this->viteManager->isRunningHot()) {
                echo $this->viteManager->getViteClientHtml();
            }
        }, 1);
    }

    public function configureViteAssets(): void
    {
        if (! $this->viteManager) {
            throw new \RuntimeException('Vite manager not initialized. Call useVite() first.');
        }

        $asset = $this->viteManager->isRunningHot()
            ? $this->viteManager->asset($this->path)
            : $this->viteManager->getAssetUrls([$this->path]);
        $this->path = $asset;
    }

    protected function needToLoadViteClient(): bool
    {
        return $this->useVite && $this->viteManager && $this->viteManager->isRunningHot();
    }

    protected function getAssetPaths(): array
    {
        if ($this->useVite && ! $this->viteManager->isRunningHot()) {
            return $this->path;
        }

        return [$this->type => [$this->path]];
    }

    protected function enqueueAsset(string $type, string $path): void
    {
        $handle = $this->useVite && ! $this->viteManager->isRunningHot() ? $this->handle.'/'.sanitize_title(basename($path)) : $this->handle;
        match ($type) {
            'css' => $this->enqueueStyle($path, $handle),
            'js' => $this->enqueueScript($path),
            default => throw new \InvalidArgumentException("Unsupported asset type: {$type}")
        };
    }

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

    protected function enqueueStyle(string $path, string $handle): void
    {
        wp_enqueue_style($handle, $path, $this->dependencies, $this->version, $this->media);

        if ($this->inlineContent !== null && $this->inlineContent !== '' && $this->inlineContent !== '0') {
            wp_add_inline_style($handle, $this->inlineContent);
        }
    }

    protected function addViteScriptAttributes(): void
    {
        Filter::add('script_loader_tag', fn ($tag, $handle, $src) => $handle === $this->handle
            ? '<script type="module" crossorigin src="'.esc_url($src).'"></script>'
            : $tag, 10, 3);
    }

    protected function forceFullUrl(string $path): string
    {
        if (str_contains($path, '://')) {
            return $path;
        }

        $basePath = $this->container->getBasePath();
        $fullPath = $basePath.'/'.ltrim($path, '/');

        return home_url($fullPath);
    }

    protected function determineFileType(string $path): string
    {
        $type = pathinfo($path, PATHINFO_EXTENSION);

        if (! in_array($type, ['css', 'js'])) {
            throw new \InvalidArgumentException("Unsupported file type: {$type}");
        }

        return $type;
    }
}
