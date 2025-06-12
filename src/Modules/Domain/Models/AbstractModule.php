<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Models;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Pollora\Modules\Domain\Contracts\ModuleInterface;

abstract class AbstractModule implements ModuleInterface
{
    protected array $metadata = [];

    public function __construct(
        protected string $name,
        protected string $path
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getLowerName(): string
    {
        return strtolower($this->name);
    }

    public function getStudlyName(): string
    {
        return Str::studly($this->name);
    }

    public function getKebabName(): string
    {
        return Str::kebab($this->name);
    }

    public function getSnakeName(): string
    {
        return Str::snake($this->name);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    public function set(string $key, mixed $value): static
    {
        data_set($this->metadata, $key, $value);

        return $this;
    }

    public function getDescription(): string
    {
        return $this->get('description', '');
    }

    protected function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    protected function getMetadata(): array
    {
        return $this->metadata;
    }

    public function __toString(): string
    {
        return $this->getStudlyName();
    }
}
