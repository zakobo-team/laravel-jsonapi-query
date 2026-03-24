<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Scopes\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

interface JsonApiScope
{
    public function shouldApply(Request $request): bool;

    public function apply(Builder $query, Request $request): void;
}
