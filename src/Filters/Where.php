<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters;

use Illuminate\Database\Eloquent\Builder;
use Zakobo\JsonApiQuery\Filters\Concerns\DeserializesValue;
use Zakobo\JsonApiQuery\Filters\Contracts\Filter;

class Where implements Filter
{
    use DeserializesValue;

    protected string $operator = '=';

    protected function __construct(
        protected readonly string $key,
        protected readonly ?string $column = null,
    ) {
    }

    public static function make(string $key, ?string $column = null): static
    {
        return new static($key, $column);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function gt(): static
    {
        $this->operator = '>';

        return $this;
    }

    public function gte(): static
    {
        $this->operator = '>=';

        return $this;
    }

    public function lt(): static
    {
        $this->operator = '<';

        return $this;
    }

    public function lte(): static
    {
        $this->operator = '<=';

        return $this;
    }

    public function apply(Builder $query, mixed $value): void
    {
        $column = $query->getModel()->qualifyColumn($this->column ?? $this->key);

        $query->where($column, $this->operator, $this->deserialize($value));
    }
}
