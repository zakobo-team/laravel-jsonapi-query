<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Zakobo\JsonApiQuery\Filters\Scope;
use Zakobo\JsonApiQuery\QueryConfig\HasJsonApiQueryConfiguration;
use Zakobo\JsonApiQuery\QueryConfig\ProvidesJsonApiQueryConfiguration;
use Zakobo\JsonApiQuery\Sorting\ScopeSort;

class ConfigurablePlainPostResource extends JsonApiResource implements ProvidesJsonApiQueryConfiguration
{
    use HasJsonApiQueryConfiguration;

    public function __construct($resource)
    {
        parent::__construct($resource);

        $this->excludedFromFilter = ['created_at'];
        $this->excludedFromSorting = ['votes'];
        $this->additionalFilters = [
            'popular' => Scope::class,
        ];
        $this->additionalSorts = [
            'latest-comment' => ScopeSort::class,
        ];
        $this->defaultSort = '-latest-comment';
        $this->defaultPageSize = 5;
        $this->maxPageSize = 10;
    }

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
            'comments',
            'user' => UserResource::class,
        ];
    }
}
