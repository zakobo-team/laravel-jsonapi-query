<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\JsonApiQueryResource;

class PostConventionalResource extends JsonApiQueryResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'title',
            'slug',
            'created_at',
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'comments',
            'user' => UserResource::class,
        ];
    }
}
