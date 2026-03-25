<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\JsonApiQueryResource;

class UserResource extends JsonApiQueryResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'name',
            'email',
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'country' => CountryResource::class,
            'posts' => PostResource::class,
        ];
    }
}
