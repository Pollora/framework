<?php

declare(strict_types=1);

namespace Pollen\Theme;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ThemeMetadata
{
    protected string $name;

    protected array $config = [];

    public function __construct(string $name, protected string $basePath)
    {
        $this->name = Str::snake(strtolower($name));
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getParentTheme(): ?string
    {
        return $this->config['parent'] ?? null;
    }

    public function getBasePath(): string
    {
        return "{$this->basePath}/{$this->name}";
    }

    public function getPathForItem(string|array|null $pathParts = null): string
    {
        $pathParts = Arr::wrap($pathParts);
        $folders = empty($pathParts) ? '' : implode('/', $pathParts);

        return $this->getBasePath().'/'.$folders;
    }

    public function getConfigPath(): string
    {
        return $this->getPathForItem('config/config.php');
    }

    public function loadConfiguration(): void
    {
        $this->config = $this->safeLoadConfig($this->getConfigPath());
    }

    protected function safeLoadConfig(string $path): array
    {
        return file_exists($path) ? include $path : [];
    }

    public function getViewPath(): string
    {
        return $this->getPathForItem('views');
    }

    public function getLanguagePath(): string
    {
        return $this->getPathForItem('lang');
    }
}
