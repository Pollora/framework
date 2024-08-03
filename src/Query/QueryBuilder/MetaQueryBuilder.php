<?php

declare(strict_types=1);

namespace Pollen\Query\QueryBuilder;

class MetaQueryBuilder extends SubQuery
{
    protected $type = 'char';

    const NUMERIC = 'NUMERIC';

    const BINARY = 'BINARY';

    const CHAR = 'CHAR';

    const DATE = 'DATE';

    const DATETIME = 'DATETIME';

    const DECIMAL = 'DECIMAL';

    const SIGNED = 'SIGNED';

    const TIME = 'TIME';

    const UNSIGNED = 'UNSIGNED';

    protected ?string $state = null;

    public function __construct(
        private mixed $key,
        private mixed $value = null,
    ) {}

    private function compareWith(string $compare, mixed $value, ?string $type = null): self
    {
        $this->compare = $compare;
        if ($type !== null) {
            $this->type = $this->type !== 'CHAR' ? $this->type : $type;
        }
        $this->value = $value;

        return $this;
    }

    public function greaterThan(mixed $value): self
    {
        return $this->compareWith(self::GREATER, $value, self::NUMERIC);
    }

    public function greaterOrEqualTo(mixed $value): self
    {
        return $this->compareWith(self::GREATER_EQUAL, $value, self::NUMERIC);
    }

    public function lessThan(mixed $value): self
    {
        return $this->compareWith(self::LESS, $value, self::NUMERIC);
    }

    public function lessOrEqualTo(mixed $value): self
    {
        return $this->compareWith(self::LESS_EQUAL, $value, self::NUMERIC);
    }

    public function equalTo(mixed $value): self
    {
        return $this->compareWith(self::EQUAL, $value, self::NUMERIC);
    }

    public function notEqualTo(mixed $value): self
    {
        return $this->compareWith(self::NOT_EQUAL, $value, self::NUMERIC);
    }

    public function between(mixed $lowerBoundary, mixed $upperBoundary): self
    {
        return $this->compareWith(self::BETWEEN, [
            $lowerBoundary,
            $upperBoundary,
        ], self::NUMERIC);
    }

    public function notBetween(mixed $lowerBoundary, mixed $upperBoundary): self
    {
        return $this->compareWith(self::NOT_BETWEEN, [
            $lowerBoundary,
            $upperBoundary,
        ], self::NUMERIC);
    }

    public function like(string $value): self
    {
        return $this->compareWith(self::LIKE, $value);
    }

    public function notLike(string $value): self
    {
        return $this->compareWith(self::NOT_LIKE, $value);
    }

    public function in(array $value): self
    {
        $this->compare = self::IN;
        $this->value = $value;

        return $this;
    }

    public function notIn(array $value): self
    {
        return $this->compareWith(self::NOT_IN, $value);
    }

    public function state(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function exists(): self
    {
        return $this->compareWith(self::EXISTS, null);
    }

    public function notExists(): self
    {
        return $this->compareWith(self::NOT_EXISTS, null);
    }

    public function get(): array
    {
        $config = [
            'key' => $this->key,
            'compare' => $this->compare,
            'type' => strtoupper($this->type),
            'state' => $this->state,
        ];

        if ($this->value !== null) {
            $config['value'] = $this->value;
        }

        return $config;
    }
}
