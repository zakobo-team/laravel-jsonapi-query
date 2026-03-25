<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\JsonApiQueryResource;
use Zakobo\JsonApiQuery\Sorting\ScopeSort;

class PostDefaultScopeSortResource extends JsonApiQueryResource
{
    public function toAttributes(Request $request): array
    {
        return ['title', 'slug'];
    }

    public function toRelationships(Request $request): array
    {
        return ['comments' => CommentResource::class];
    }

    public array $additionalSorts = [
        'latest-comment' => ScopeSort::class,
    ];

    public ?string $defaultSort = '-latest-comment';
}
