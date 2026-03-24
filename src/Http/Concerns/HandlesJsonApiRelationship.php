<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Http\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\Filters\FilterDispatcher;
use Zakobo\JsonApiQuery\JsonApiQueryResource;
use Zakobo\JsonApiQuery\Pagination\JsonApiPaginator;
use Zakobo\JsonApiQuery\Sorting\SortApplier;

trait HandlesJsonApiRelationship
{
    public function index(Request $request): mixed
    {
        $resourceClass = $this->getResource();
        $parentModelClass = $this->getParentModel();

        /** @var JsonApiQueryResource $config */
        $config = $resourceClass::queryConfig();

        $parent = $this->resolveParent($parentModelClass, $request->route($this->getParentRouteParameter()));

        if ($parent === null) {
            abort(404);
        }

        /** @var Builder $query */
        $query = $parent->{$this->getParentRelationship()}()->getQuery();

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

    /**
     * Resolve the parent model from the route parameter value.
     */
    protected function resolveParent(string $parentModelClass, mixed $routeValue): ?Model
    {
        if ($routeValue instanceof Model) {
            return $routeValue;
        }

        if ($routeValue === null) {
            return null;
        }

        return $parentModelClass::find($routeValue);
    }
}
