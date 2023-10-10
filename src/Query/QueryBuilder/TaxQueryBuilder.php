<?php

declare(strict_types=1);

namespace Pollen\Query\QueryBuilder;

class TaxQueryBuilder extends SubQuery
{
    const SEARCH_BY_SLUG = 'slug';

    const SEARCH_BY_NAME = 'name';

    const SEARCH_BY_TERM_TAX_ID = 'term_taxonomy_id';

    const SEARCH_BY_ID = 'term_id';

    private $field = 'term_id';

    private $operator = 'IN';

    private $includeChildren = true;

    public function __construct(
        private $taxonomy
    ) {
    }

    public function notExists()
    {
        $this->operator = 'NOT EXISTS';

        return $this;
    }

    public function exists()
    {
        $this->operator = 'EXISTS';

        return $this;
    }

    public function field($field)
    {

        $allowed = [self::SEARCH_BY_ID, self::SEARCH_BY_NAME, self::SEARCH_BY_TERM_TAX_ID, self::SEARCH_BY_SLUG];

        if (! in_array($field, $allowed)) {
            throw new \Exception('Invalid tax field type supplied: '.$field);
        }

        $this->field = $field;

        return $this;
    }

    public function contains($terms)
    {
        $this->terms = $terms;
        $this->operator = 'IN';

        return $this;
    }

    public function notContains($terms)
    {
        $this->terms = $terms;
        $this->operator = 'NOT IN';

        return $this;
    }

    public function termSlugs()
    {
        $this->field(self::SEARCH_BY_SLUG);

        return $this;
    }

    public function termNames()
    {
        $this->field(self::SEARCH_BY_NAME);

        return $this;
    }

    public function termTaxIds()
    {
        $this->field(self::SEARCH_BY_TERM_TAX_ID);

        return $this;
    }

    public function termIds()
    {
        $this->field(self::SEARCH_BY_ID);

        return $this;
    }

    public function includeChildren($field)
    {
        $this->includeChildren = true;

        return $this;
    }

    public function onlyParent($field)
    {
        $this->includeChildren = false;

        return $this;
    }

    public function excludeChildren($field)
    {
        $this->includeChildren = false;

        return $this;
    }

    public function get()
    {
        return [
            'taxonomy' => $this->taxonomy,
            'field' => $this->field,
            'terms' => $this->terms,
            'operator' => $this->operator,
            'include_children' => $this->includeChildren,
        ];
    }
}
