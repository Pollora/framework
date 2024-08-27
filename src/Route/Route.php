<?php

declare(strict_types=1);

namespace Pollen\Route;

use Illuminate\Http\Request;
use Illuminate\Routing\Matching\MethodValidator;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Arr;
use Pollen\Route\Matching\ConditionValidator;

class Route extends IlluminateRoute
{
    protected array $conditions = [];

    protected string $condition = '';

    protected array $conditionParams = [];

    protected ?array $wordpressValidators = null;

    public function matches(Request $request, $includingMethod = true): bool
    {
        $this->compileRoute();

        if ($this->hasCondition()) {
            return $this->matchesWordPressConditions($request);
        }

        return $this->matchesIlluminateValidators($request, $includingMethod);
    }

    public function hasCondition(): bool
    {
        return ! empty($this->condition);
    }

    public function setConditions(array $conditions = []): self
    {
        $this->conditions = $conditions;
        $this->condition = $this->parseCondition($this->uri());
        $this->conditionParams = $this->parseConditionParams($this->getAction());

        return $this;
    }

    public function getCondition(): string
    {
        return $this->condition;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function getConditionParameters(): array
    {
        return $this->conditionParams;
    }

    public function getWordPressValidators(): array
    {
        return $this->wordpressValidators ??= [new ConditionValidator];
    }

    protected function parseCondition(string $condition): string
    {
        foreach ($this->getConditions() as $signature => $conds) {
            $conds = (array) $conds;
            if (in_array($condition, $conds, true)) {
                return $signature;
            }
        }

        return '';
    }

    protected function parseConditionParams(array $action): array
    {
        if (empty($this->condition)) {
            return [];
        }

        $params = Arr::first($action, fn ($value, $key) => is_numeric($key));

        return [$params];
    }

    private function matchesWordPressConditions(Request $request): bool
    {
        foreach ($this->getWordPressValidators() as $validator) {
            if (! $validator->matches($this, $request)) {
                return false;
            }
        }

        return true;
    }

    private function matchesIlluminateValidators(Request $request, bool $includingMethod): bool
    {
        foreach ($this->getValidators() as $validator) {
            if (! $includingMethod && $validator instanceof MethodValidator) {
                continue;
            }
            if (! $validator->matches($this, $request)) {
                return false;
            }
        }

        return true;
    }
}
