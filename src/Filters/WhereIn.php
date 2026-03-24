<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters;

use Illuminate\Database\Eloquent\Builder;
use Zakobo\JsonApiQuery\Filters\Concerns\DeserializesValue;
use Zakobo\JsonApiQuery\Filters\Concerns\ResolvesArrayValues;
use Zakobo\JsonApiQuery\Filters\Contracts\Filter;

class WhereIn implements Filter
{
    use DeserializesValue;
    use ResolvesArrayValues;

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

    public function apply(Builder $query, mixed $value): void
    {
        $column = $query->getModel()->qualifyColumn($this->column ?? $this->key);

        $query->whereIn($column, $this->resolveValues($value));
    }
}
