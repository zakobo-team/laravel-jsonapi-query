<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Zakobo\JsonApiQuery\Filters\Concerns\DeserializesValue;
use Zakobo\JsonApiQuery\Filters\Contracts\Filter;

class Scope implements Filter
{
    use DeserializesValue;

    public function __construct(
        protected readonly string $key,
        protected readonly ?string $scopeName = null,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function apply(Builder $query, mixed $value): void
    {
        $scope = $this->scopeName ?? Str::camel($this->key);

        $query->{$scope}($this->deserialize($value));
    }
}
