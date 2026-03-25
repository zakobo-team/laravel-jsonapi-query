<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\JsonApiQueryResource;

class PostMetaResource extends JsonApiQueryResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'seo_title',
            'seo_description',
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'post' => PostResource::class,
        ];
    }
}
