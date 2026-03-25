<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Includes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Zakobo\JsonApiQuery\Filters\Where;
use Zakobo\JsonApiQuery\Schema\ResourceSchema;
use Zakobo\JsonApiQuery\Schema\ResourceSchemaFactory;

class IncludeApplier
{
    protected const OPERATOR_KEYS = ['gt', 'gte', 'lt', 'lte', 'eq'];

    public function __construct(
        protected readonly ResourceSchemaFactory $resourceSchemas,
    ) {
    }

    public function apply(Builder $query, ResourceSchema $schema, JsonApiRequest $request): void
    {
        $includeTree = $this->buildIncludeTree($this->requestedIncludePaths($request));

        if ($includeTree === []) {
            return;
        }

        $filtersByPath = $this->includeFiltersByPath($request);
        $eagerLoads = $this->buildEagerLoads($schema, $request, $includeTree, $filtersByPath);

        if ($eagerLoads !== []) {
            $query->with($eagerLoads);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildIncludeTree(array $paths): array
    {
        $tree = [];

        foreach ($paths as $path) {
            $segments = explode('.', $path);
            $current = &$tree;

            foreach ($segments as $segment) {
                $current[$segment] ??= [];
                $current = &$current[$segment];
            }
        }

        return $tree;
    }

    /**
     * @param  array<string, mixed>  $includeTree
     * @param  array<string, array<string, mixed>>  $filtersByPath
     * @return array<string, mixed>
     */
    protected function buildEagerLoads(
        ResourceSchema $schema,
        JsonApiRequest $request,
        array $includeTree,
        array $filtersByPath,
        string $currentPath = '',
    ): array {
        $loads = [];

        foreach ($includeTree as $relationshipName => $children) {
            $path = $currentPath === ''
                ? $relationshipName
                : "{$currentPath}.{$relationshipName}";

            $relationship = $schema->relationship($relationshipName);

            if ($relationship === null || $relationship->resourceClass === null) {
                continue;
            }

            $relatedSchema = $this->resourceSchemas->schemaForRelationship($relationship, $request);
            $nestedLoads = $this->buildEagerLoads($relatedSchema, $request, $children, $filtersByPath, $path);
            $filters = $filtersByPath[$path] ?? [];

            if ($nestedLoads === [] && $filters === []) {
                $loads[] = $relationshipName;

                continue;
            }

            $loads[$relationshipName] = function (Builder|Relation $relationQuery) use ($nestedLoads, $filters) {
                if ($filters !== []) {
                    $this->applyConstraintsToRelationshipQuery($relationQuery, $filters);
                }

                if ($nestedLoads !== []) {
                    $relationQuery->with($nestedLoads);
                }
            };
        }

        return $loads;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function includeFiltersByPath(JsonApiRequest $request): array
    {
        $includeFilters = $request->query('includeFilter');

        if (! is_array($includeFilters)) {
            return [];
        }

        $grouped = [];

        foreach ($includeFilters as $key => $value) {
            if (! is_string($key) || ! str_contains($key, '.')) {
                continue;
            }

            $segments = explode('.', $key);
            $attribute = array_pop($segments);
            $path = implode('.', $segments);

            $grouped[$path][$attribute] = $value;
        }

        return $grouped;
    }

    /**
     * @return array<string>
     */
    protected function requestedIncludePaths(JsonApiRequest $request): array
    {
        $include = trim((string) $request->query('include', ''));

        if ($include === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $include))));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyConstraintsToRelationshipQuery(Builder|Relation $query, array $filters): void
    {
        $builder = $query instanceof Relation ? $query->getQuery() : $query;

        foreach ($filters as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_array($value) && $this->containsOperatorKeys($value)) {
                foreach ($value as $operator => $operatorValue) {
                    if (! in_array($operator, self::OPERATOR_KEYS, true)) {
                        continue;
                    }

                    $filter = Where::make($key);
                    $filter = match ($operator) {
                        'gt' => $filter->gt(),
                        'gte' => $filter->gte(),
                        'lt' => $filter->lt(),
                        'lte' => $filter->lte(),
                        'eq' => $filter,
                    };
                    $filter->apply($builder, $operatorValue);
                }

                continue;
            }

            Where::make($key)->apply($builder, $value);
        }
    }

    protected function containsOperatorKeys(array $value): bool
    {
        foreach (array_keys($value) as $key) {
            if (in_array($key, self::OPERATOR_KEYS, true)) {
                return true;
            }
        }

        return false;
    }
}
