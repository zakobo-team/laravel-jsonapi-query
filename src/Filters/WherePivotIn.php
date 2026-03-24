<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Zakobo\JsonApiQuery\Filters\Concerns\DeserializesValue;
use Zakobo\JsonApiQuery\Filters\Concerns\ResolvesArrayValues;
use Zakobo\JsonApiQuery\Filters\Contracts\PivotFilter;

class WherePivotIn implements PivotFilter
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

    public function applyToRelation(BelongsToMany $relation, mixed $value): void
    {
        $relation->wherePivotIn($this->column ?? $this->key, $this->resolveValues($value));
    }
}
