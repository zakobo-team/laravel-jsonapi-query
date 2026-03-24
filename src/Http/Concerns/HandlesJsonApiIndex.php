<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Http\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\Filters\FilterDispatcher;
use Zakobo\JsonApiQuery\JsonApiQueryResource;
use Zakobo\JsonApiQuery\Pagination\JsonApiPaginator;
use Zakobo\JsonApiQuery\Scopes\Contracts\JsonApiScope;
use Zakobo\JsonApiQuery\Sorting\SortApplier;

trait HandlesJsonApiIndex
{
    public function index(Request $request): mixed
    {
        $resourceClass = $this->getResource();
        $modelClass = $this->getModel();

        /** @var JsonApiQueryResource $config */
        $config = $resourceClass::queryConfig();

        $query = $modelClass::query();

        $this->applyScopes($query, $config, $request);

        $this->applyFilters($query, $config, $request);

        SortApplier::apply(
            $query,
            $config->sortableAttributes(),
            $request,
            $config->defaultSort,
        );

        $paginator = JsonApiPaginator::apply(
            $query,
            $request,
            $config->defaultPageSize ?? (int) config('jsonapi-query.pagination.default_size', 30),
            $config->maxPageSize ?? (int) config('jsonapi-query.pagination.max_size', 100),
        );

        return $resourceClass::collection($paginator);
    }

    protected function applyScopes(Builder $query, JsonApiQueryResource $config, Request $request): void
    {
        foreach ($config->scopedBy as $scopeClass) {
            /** @var JsonApiScope $scope */
            $scope = new $scopeClass;

            if ($scope->shouldApply($request)) {
                $scope->apply($query, $request);
            }
        }
    }

    protected function applyFilters(Builder $query, JsonApiQueryResource $config, Request $request): void
    {
        $additionalFilterInstances = [];

        foreach ($config->additionalFilters as $key => $filterClass) {
            $additionalFilterInstances[] = $filterClass::make($key);
        }

        FilterDispatcher::make()
            ->attributes($config->filterableAttributes())
            ->relationships($config->filterableRelationships())
            ->additionalFilters($additionalFilterInstances)
            ->apply($query, $request);
    }
}
