<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters;

use Illuminate\Database\Eloquent\Builder;
use Zakobo\JsonApiQuery\Filters\Concerns\DetectsSoftDeletes;
use Zakobo\JsonApiQuery\Filters\Contracts\Filter;

class OnlyTrashed implements Filter
{
    use DetectsSoftDeletes;

    protected function __construct(
        protected readonly string $key = 'only-trashed',
    ) {
    }

    public static function make(string $key = 'only-trashed'): static
    {
        return new static($key);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function apply(Builder $query, mixed $value): void
    {
        if (! $this->modelUsesSoftDeletes($query)) {
            return;
        }

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $query->onlyTrashed();
        }
    }
}
