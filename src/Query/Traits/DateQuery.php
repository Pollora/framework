<?php

declare(strict_types=1);

namespace Pollen\Query\Traits;

use Pollen\Query\QueryBuilder\SubQuery;

trait DateQuery
{
    protected $dateQuery;

    public function dateQuery(callable|SubQuery $callback): self
    {
        if (! $this->dateQuery) {
            $this->dateQuery = [];
        }

        if (! isset($this->queryBuilder['date_query'])) {
            $this->queryBuilder['date_query'] = new \Pollen\Query\DateQuery();
        }
        if ($callback instanceof QueryBuilder\SubQuery) {
            $this->dateQuery = ['relation' => 'AND'] + [$callback->get()];
        } else {
            $callback($this->queryBuilder['date_query']);

            $this->dateQuery = array_merge($this->dateQuery, $this->queryBuilder['date_query']->get());
        }

        return $this;
    }
}
