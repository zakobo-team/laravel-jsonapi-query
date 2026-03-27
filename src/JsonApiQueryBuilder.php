<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Zakobo\JsonApiQuery\Exceptions\InvalidAdditionalFilterClassException;
use Zakobo\JsonApiQuery\Exceptions\InvalidAdditionalSortClassException;
use Zakobo\JsonApiQuery\Filters\Contracts\Filter;
use Zakobo\JsonApiQuery\Filters\FilterDispatcher;
use Zakobo\JsonApiQuery\Includes\IncludeApplier;
use Zakobo\JsonApiQuery\Pagination\JsonApiPaginator;
use Zakobo\JsonApiQuery\Schema\ResourceSchema;
use Zakobo\JsonApiQuery\Schema\ResourceSchemaFactory;
use Zakobo\JsonApiQuery\Sorting\Contracts\Sort;
use Zakobo\JsonApiQuery\Sorting\SortApplier;
use Zakobo\JsonApiQuery\Validation\QueryValidator;

class JsonApiQueryBuilder
{
    public function __construct(
        protected readonly Container $container,
        protected readonly ResourceSchemaFactory $resourceSchemas,
        protected readonly QueryValidator $validator,
        protected readonly SortApplier $sortApplier,
        protected readonly IncludeApplier $includeApplier,
        protected readonly JsonApiPaginator $paginator,
    ) {
    }

    /**
     * @param  class-string<JsonApiQueryResource>|null  $resourceClass
     */
    public function collection(
        Builder $query,
        Request $request,
        ?string $resourceClass = null,
    ): AnonymousResourceCollection {
        $schema = $this->resourceSchemas->fromBuilder($query, $request, $resourceClass);

        $this->applyToQuery($query, $schema, $request);

        $defaultPageSize = $schema->defaultPageSize
            ?? (int) config('jsonapi-query.pagination.default_size', 30);
        $maxPageSize = $schema->maxPageSize
            ?? (int) config('jsonapi-query.pagination.max_size', 100);

        $paginator = $this->paginator->paginate($query, $request, $defaultPageSize, $maxPageSize);

        return $schema->resourceClass::collection($paginator);
    }

    /**
     * @param  class-string<JsonApiQueryResource>|null  $resourceClass
     */
    public function apply(
        Builder $query,
        Request $request,
        ?string $resourceClass = null,
    ): Builder {
        $schema = $this->resourceSchemas->fromBuilder($query, $request, $resourceClass);

        $this->applyToQuery($query, $schema, $request);

        return $query;
    }

    protected function applyToQuery(
        Builder $query,
        ResourceSchema $schema,
        Request $request,
    ): void {
        $jsonApiRequest = $request instanceof JsonApiRequest
            ? $request
            : JsonApiRequest::createFrom($request);

        $this->validator->validate($query, $schema, $jsonApiRequest);

        $additionalFilterInstances = $this->resolveAdditionalFilters($schema->additionalFilters);

        FilterDispatcher::make()
            ->attributes($this->validator->autoFilterableAttributes($schema))
            ->relationships(collect($schema->relationships)
                ->mapWithKeys(fn ($relationship, $name) => [$name => $relationship->relationMethodName])
                ->all())
            ->additionalFilters($additionalFilterInstances)
            ->apply($query, $jsonApiRequest);

        $additionalSortInstances = $this->resolveAdditionalSorts($schema->additionalSorts);

        $this->sortApplier->apply(
            $query,
            $this->validator->autoSortableAttributes($schema),
            $jsonApiRequest,
            $schema->defaultSort,
            $this->validator->relationshipSortableFields($schema, $jsonApiRequest),
            collect($schema->relationships)
                ->mapWithKeys(fn ($relationship, $name) => [$name => $relationship->relationMethodName])
                ->all(),
            $additionalSortInstances,
        );

        $this->includeApplier->apply($query, $schema, $jsonApiRequest);
    }

    /**
     * @param  array<string, class-string<Filter>>  $filters
     * @return array<Filter>
     */
    protected function resolveAdditionalFilters(array $filters): array
    {
        $instances = [];

        foreach ($filters as $key => $filterClass) {
            $filter = $this->container->makeWith($filterClass, ['key' => $key]);

            if (! $filter instanceof Filter) {
                throw new InvalidAdditionalFilterClassException("Configured additional filter [{$filterClass}] must implement Filter.");
            }

            $instances[] = $filter;
        }

        return $instances;
    }

    /**
     * @param  array<string, class-string<Sort>>  $sorts
     * @return array<Sort>
     */
    protected function resolveAdditionalSorts(array $sorts): array
    {
        $instances = [];

        foreach ($sorts as $key => $sortClass) {
            $sort = $this->container->makeWith($sortClass, ['key' => $key]);

            if (! $sort instanceof Sort) {
                throw new InvalidAdditionalSortClassException("Configured additional sort [{$sortClass}] must implement Sort.");
            }

            $instances[] = $sort;
        }

        return $instances;
    }
}
