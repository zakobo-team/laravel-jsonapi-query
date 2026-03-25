<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\Filters\Scope;
use Zakobo\JsonApiQuery\JsonApiQueryResource;
use Zakobo\JsonApiQuery\Sorting\ScopeSort;

class PostResource extends JsonApiQueryResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'title',
            'slug',
            'votes',
            'published',
            'created_at',
            'computed_score' => fn () => $this->votes * 2,
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'comments',
            'tags' => TagResource::class,
            'user' => UserResource::class,
            'meta' => PostMetaResource::class,
        ];
    }

    public array $excludedFromFilter = ['computed_score'];

    public array $excludedFromSorting = ['computed_score'];

    public array $additionalFilters = [
        'popular' => Scope::class,
    ];

    public ?string $defaultSort = '-created_at';

    public ?int $defaultPageSize = 15;

    public ?int $maxPageSize = 50;

    public array $additionalSorts = [
        'latest-comment' => ScopeSort::class,
    ];
}
