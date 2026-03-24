<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\Scopes\Contracts\JsonApiScope;

class NeverAppliesScope implements JsonApiScope
{
    public function shouldApply(Request $request): bool
    {
        return false;
    }

    public function apply(Builder $query, Request $request): void
    {
        $query->where('id', '<', 0);
    }
}
