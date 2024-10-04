<?php

declare(strict_types=1);

namespace Pollen\Admin;

class PageFactory
{
    public function __construct(private readonly Page $page) {}

    public function page(string $pageTitle, string $menuTitle, string $capability, string $slug, mixed $action, string $iconUrl = '', ?int $position = null): self
    {
        $this->page->addPage($pageTitle, $menuTitle, $capability, $slug, $action, $iconUrl, $position);

        return $this;
    }

    public function subpage(string $parent, string $pageTitle, string $menuTitle, string $capabilities, string $slug, mixed $action): self
    {
        $this->page->addSubpage($parent, $pageTitle, $menuTitle, $capabilities, $slug, $action);

        return $this;
    }
}
