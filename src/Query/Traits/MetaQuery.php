<?php

declare(strict_types=1);

namespace Pollen\Query\Traits;

use Pollen\Query\QueryBuilder;

trait MetaQuery
{
    protected $metaQuery;

    public function metaQuery(callable|QueryBuilder\SubQuery $callback): self
    {
        if (! $this->metaQuery) {
            $this->metaQuery = [];
        }

        if (! isset($this->queryBuilder['meta_query'])) {
            $this->queryBuilder['meta_query'] = new \Pollen\Query\MetaQuery();
        }
        if ($callback instanceof QueryBuilder\SubQuery) {
            $this->metaQuery = ['relation' => 'AND'] + [$callback->get()];
        } else {
            $callback($this->queryBuilder['meta_query']);

            $this->metaQuery = array_merge($this->metaQuery, $this->queryBuilder['meta_query']->get());
        }

        return $this;
    }
}
