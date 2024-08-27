<?php

declare(strict_types=1);

namespace Pollen\Asset;

use Illuminate\Support\Facades\Vite;
use Pollen\Support\Facades\Action;
use Pollen\Support\Facades\Filter;

class Asset
{
    protected string $handle = '';

    protected string $path = '';

    protected string $type = 'style';

    protected array $dependencies = [];

    protected ?\Pollen\Asset\Vite $vite = null;

    protected bool $requireViteClient = false;

    protected ?string $theme = null;

    protected bool $useVite = false;

    protected ?string $version = null;

    protected string $media = 'all';

    protected bool $loadInFooter = false;

    protected ?string $loadStrategy = null;

    protected ?string $inlineContent = null;

    protected ?string $inlinePosition = null;

    protected array $hooks = [];

    public function __construct(string $handle, string $path)
    {
        $this->handle = $handle;
        $this->path = str_replace(base_path('/'), '', $path);
        $this->type = $this->determineFileType($path);
        $this->vite = app('wp.vite');
        $this->vite->setClient($path);
    }

    public function dependencies(array $dependencies): self
    {
        $this->dependencies = $dependencies;

        return $this;
    }

    public function useVite(): self
    {
        $this->useVite = true;
        $this->path = Vite::isRunningHot()
            ? $this->vite->retrieveHotAsset($this->path)
            : $this->vite->lookupAssetInManifest($this->path);

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
        $this->hooks[] = 'wp_enqueue_scripts';

        return $this;
    }

    public function toBackend(): self
    {
        $this->hooks[] = 'admin_enqueue_scripts';

        return $this;
    }

    public function toLoginScreen(): self
    {
        $this->hooks[] = 'login_enqueue_scripts';

        return $this;
    }

    public function toCustomizer(): self
    {
        $this->hooks[] = 'customize_preview_init';

        return $this;
    }

    public function toEditor(): self
    {
        $this->hooks[] = 'enqueue_block_editor_assets';

        return $this;
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
        if (empty($this->hooks)) {
            $this->hooks[] = 'wp_enqueue_scripts';
        }

        foreach ($this->hooks as $hook) {
            $this->maybeLoadViteClient($hook);
            Action::add($hook, [$this, 'enqueueStyleOrScript'], 99);
        }
    }

    protected function needToLoadViteClient(string $hook): bool
    {
        return $this->useVite && Vite::isRunningHot() && ! $this->vite->loadedInHook($hook);
    }

    protected function maybeLoadViteClient(string $hook): void
    {
        Action::add($hook, function () use ($hook) {
            if ($this->needToLoadViteClient($hook)) {
                echo $this->vite->viteClientHtml($hook)->toHtml();
            }
        }, 1);
    }

    public function enqueueStyleOrScript(): void
    {
        match ($this->type) {
            'style' => $this->enqueueStyle(),
            'script' => $this->enqueueScript(),
            default => throw new \InvalidArgumentException("Unsupported asset type: {$this->type}")
        };
    }

    protected function enqueueScript(): void
    {
        wp_enqueue_script($this->handle, $this->path, $this->dependencies, $this->version, $this->loadInFooter);

        if ($this->useVite) {
            Filter::add('script_loader_tag', function ($tag, $handle, $src) {
                if ($handle !== $this->handle) {
                    return $tag;
                }

                return '<script type="module" crossorigin src="'.esc_url($src).'"></script>';
            }, 10, 3);
        }

        if ($this->loadStrategy) {
            wp_script_add_data($this->handle, 'defer', true);
        }
        if ($this->inlineContent) {
            wp_add_inline_script($this->handle, $this->inlineContent, $this->inlinePosition);
        }
    }

    protected function enqueueStyle(): void
    {
        wp_enqueue_style($this->handle, $this->path, $this->dependencies, $this->version, $this->media);

        if ($this->inlineContent) {
            wp_add_inline_style($this->handle, $this->inlineContent);
        }
    }

    protected function determineFileType(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return match ($extension) {
            'css' => 'style',
            'js' => 'script',
            default => 'style',
        };
    }
}
