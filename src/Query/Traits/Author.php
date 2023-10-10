<?php

declare(strict_types=1);

namespace Pollen\Query\Traits;

trait Author
{
    protected $author;

    protected $authorName;

    protected $authorIn;

    protected $authorNotIn;

    public function author(int $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function authorName(string $authorName): self
    {
        $this->authorName = $authorName;

        return $this;
    }

    public function authorIn(array $authorIn): self
    {
        $this->authorIn = $authorIn;

        return $this;
    }

    public function authorNotIn(array $authorNotIn): self
    {
        $this->authorNotIn = $authorNotIn;

        return $this;
    }
}
