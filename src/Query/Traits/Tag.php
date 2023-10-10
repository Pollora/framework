<?php

declare(strict_types=1);

namespace Pollen\Query\Traits;

trait Tag
{
    protected $tag;

    protected $tagId;

    protected $tagAnd;

    protected $tagIn;

    protected $tagNotIn;

    protected $tagSlugAnd;

    protected $tagSlugIn;

    public function tag(string $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    public function tagId(int $tagId): self
    {
        $this->tagId = $tagId;

        return $this;
    }

    public function tagAnd(array $tagAnd): self
    {
        $this->tagAnd = $tagAnd;

        return $this;
    }

    public function tagIn(array $tagIn): self
    {
        $this->tagIn = $tagIn;

        return $this;
    }

    public function tagNotIn(array $tagNotIn): self
    {
        $this->tagNotIn = $tagNotIn;

        return $this;
    }

    public function tagSlugAnd(array $tagSlugAnd): self
    {
        $this->tagSlugAnd = $tagSlugAnd;

        return $this;
    }

    public function tagSlugIn(array $tagSlugIn): self
    {
        $this->tagSlugIn = $tagSlugIn;

        return $this;
    }
}
