<?php

declare(strict_types=1);

namespace Pollen\Query\Traits;

trait Post
{
    protected $postType;

    protected $p;

    protected $name;

    protected $postParent;

    protected $postParentIn;

    protected $postParentNotIn;

    protected $postIn;

    protected $postNotIn;

    protected $postNameIn;

    public function postType(string|array $postType): self
    {
        $this->postType = $postType;

        return $this;
    }

    public function postId(int $postId): self
    {
        $this->p = $postId;

        return $this;
    }

    public function postSlug(string $postName): self
    {
        $this->name = $postName;

        return $this;
    }

    public function postParent(int $postParent): self
    {
        $this->postParent = $postParent;

        return $this;
    }

    public function whereParentIn(array $postParentIn): self
    {
        $this->postParentIn = $postParentIn;

        return $this;
    }

    public function whereParentNotIn(array $postParentNotIn): self
    {
        $this->postParentNotIn = $postParentNotIn;

        return $this;
    }

    public function whereIn(array $postIn): self
    {
        $this->postIn = $postIn;

        return $this;
    }

    public function whereNotIn(array $postNotIn): self
    {
        $this->postNotIn = $postNotIn;

        return $this;
    }

    public function slugIn(array $postSlugIn): self
    {
        $this->postNameIn = $postSlugIn;

        return $this;
    }
}
