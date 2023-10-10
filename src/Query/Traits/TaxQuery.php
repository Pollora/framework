<?php

declare(strict_types=1);

namespace Pollen\Query\Traits;

use Pollen\Query\QueryBuilder;

trait TaxQuery
{
    protected $taxQuery;

    public function taxQuery(callable|QueryBuilder\SubQuery $callback): self
    {
        if (! $this->taxQuery) {
            $this->taxQuery = [];
        }

        if (! isset($this->queryBuilder['tax_query'])) {
            $this->queryBuilder['tax_query'] = new \Pollen\Query\TaxQuery();
        }
        if ($callback instanceof QueryBuilder\SubQuery) {
            $this->taxQuery = ['relation' => 'AND'] + [$callback->get()];
        } else {
            $callback($this->queryBuilder['tax_query']);

            $this->taxQuery = array_merge($this->taxQuery, $this->queryBuilder['tax_query']->get());
        }

        return $this;
    }
}
