<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class SnakeCasePostResource extends JsonApiResource
{
    public function toId(Request $request): ?string
    {
        return (string) $this->resource->getRouteKey();
    }

    public function toType(Request $request): ?string
    {
        return 'posts';
    }

    public function toAttributes(Request $request): array
    {
        return [
            'title',
            'slug',
            'votes',
            'created_at',
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'author_user' => UserResource::class,
        ];
    }
}
