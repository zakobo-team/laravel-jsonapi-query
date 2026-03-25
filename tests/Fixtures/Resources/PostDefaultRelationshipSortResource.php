<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\JsonApiQueryResource;

class PostDefaultRelationshipSortResource extends JsonApiQueryResource
{
    public function toAttributes(Request $request): array
    {
        return ['title', 'slug'];
    }

    public function toRelationships(Request $request): array
    {
        return ['user' => UserResource::class];
    }

    public ?string $defaultSort = '-user.name';
}
