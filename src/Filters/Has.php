<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters;

use Illuminate\Database\Eloquent\Builder;
use Zakobo\JsonApiQuery\Filters\Contracts\Filter;

class Has implements Filter
{
    protected function __construct(
        protected readonly string $key,
        protected readonly ?string $relationship = null,
    ) {
    }

    public static function make(string $key, ?string $relationship = null): static
    {
        return new static($key, $relationship);
    }

    public function key(): string
    {
        return $this->key;
    }

    protected function relationshipName(): string
    {
        return $this->relationship ?? $this->key;
    }

    public function apply(Builder $query, mixed $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $query->has($this->relationshipName());
        } else {
            $query->doesntHave($this->relationshipName());
        }
    }
}
