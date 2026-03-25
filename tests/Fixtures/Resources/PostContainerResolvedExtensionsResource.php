<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\JsonApiQueryResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Filters\TitleMatchesFilter;
use Zakobo\JsonApiQuery\Tests\Fixtures\Sorting\TitleLengthSort;

class PostContainerResolvedExtensionsResource extends JsonApiQueryResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'title',
            'slug',
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [];
    }

    public array $additionalFilters = [
        'title-match' => TitleMatchesFilter::class,
    ];

    public array $additionalSorts = [
        'title-length' => TitleLengthSort::class,
    ];
}
