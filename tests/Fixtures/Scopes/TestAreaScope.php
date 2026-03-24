<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\Scopes\Contracts\JsonApiScope;

class TestAreaScope implements JsonApiScope
{
    public function shouldApply(Request $request): bool
    {
        return $request->header('X-Test-Area') === 'public';
    }

    public function apply(Builder $query, Request $request): void
    {
        $query->where('published', true);
    }
}
