<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Schema;

use ArrayObject;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use JsonSerializable;
use LogicException;
use Zakobo\JsonApiQuery\Filters\OnlyTrashed;
use Zakobo\JsonApiQuery\Filters\WithTrashed;
use Zakobo\JsonApiQuery\QueryConfig\ProvidesJsonApiQueryConfiguration;

class ResourceSchemaFactory
{
    /** @var array<string, ResourceSchema> */
    protected array $cache = [];

    public function fromBuilder(Builder $query, Request $request, ?string $resourceClass = null): ResourceSchema
    {
        return $this->fromModel(
            $query->getModel(),
            $request,
            $resourceClass,
        );
    }

    /**
     * @param  class-string<JsonApiResource>|null  $resourceClass
     */
    public function fromModel(Model $model, Request $request, ?string $resourceClass = null): ResourceSchema
    {
        $resourceClass ??= $this->inferResourceClass($model);

        $cacheKey = $resourceClass.'|'.$model::class;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $jsonApiRequest = $request instanceof JsonApiRequest
            ? $request
            : JsonApiRequest::createFrom($request);

        /** @var JsonApiResource $resource */
        $resource = $resourceClass::make($model);

        $attributes = $this->normalizeAttributes($resource->toAttributes($jsonApiRequest), $model);
        $relationships = $this->normalizeRelationships($resource->toRelationships($jsonApiRequest), $model);

        $queryConfiguration = $resource instanceof ProvidesJsonApiQueryConfiguration
            ? $resource->jsonApiQueryConfiguration()
            : [];

        return $this->cache[$cacheKey] = new ResourceSchema(
            resourceClass: $resourceClass,
            modelClass: $model::class,
            attributes: $attributes,
            relationships: $relationships,
            additionalFilters: array_replace(
                $this->conventionalAdditionalFilters($model),
                $queryConfiguration['additional_filters'] ?? [],
            ),
            additionalSorts: $queryConfiguration['additional_sorts'] ?? [],
            excludedFromFilter: $queryConfiguration['excluded_from_filter'] ?? [],
            excludedFromSorting: $queryConfiguration['excluded_from_sorting'] ?? [],
            defaultSort: $queryConfiguration['default_sort'] ?? null,
            defaultPageSize: $queryConfiguration['default_page_size'] ?? null,
            maxPageSize: $queryConfiguration['max_page_size'] ?? null,
        );
    }

    public function schemaForRelationship(RelationshipSchema $relationship, Request $request): ResourceSchema
    {
        $modelClass = $relationship->relatedModelClass;
        $resourceClass = $relationship->resourceClass;

        /** @var Model $model */
        $model = new $modelClass;

        return $this->fromModel($model, $request, $resourceClass);
    }

    /**
     * @return class-string<JsonApiResource>
     */
    protected function inferResourceClass(Model $model): string
    {
        $resource = $model->toResource();

        return $resource::class;
    }

    /**
     * @return array<string, AttributeSchema>
     */
    protected function normalizeAttributes(mixed $attributes, Model $model): array
    {
        $normalized = [];

        foreach ($this->normalizeToArray($attributes) as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $normalized[$value] = new AttributeSchema(
                    name: $value,
                    autoQueryable: $this->isDirectModelColumn($model, $value),
                );

                continue;
            }

            if (is_string($key)) {
                $normalized[$key] = new AttributeSchema(
                    name: $key,
                    autoQueryable: false,
                );
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, RelationshipSchema>
     */
    protected function normalizeRelationships(mixed $relationships, Model $model): array
    {
        $normalized = [];

        foreach ($this->normalizeToArray($relationships) as $key => $value) {
            $relationshipName = null;
            $resourceClass = null;

            if (is_int($key) && is_string($value)) {
                $relationshipName = $value;
            } elseif (is_string($key)) {
                $relationshipName = $key;

                if (is_string($value) && class_exists($value) && is_subclass_of($value, JsonApiResource::class)) {
                    $resourceClass = $value;
                }
            }

            if (! is_string($relationshipName) || ! method_exists($model, $relationshipName)) {
                continue;
            }

            $relation = Relation::noConstraints(fn () => $model->{$relationshipName}());

            if (! $relation instanceof Relation) {
                continue;
            }

            $resourceClass ??= $this->inferRelatedResourceClass($relation->getRelated());

            $normalized[$relationshipName] = new RelationshipSchema(
                name: $relationshipName,
                relatedModelClass: $relation->getRelated()::class,
                resourceClass: $resourceClass,
            );
        }

        return $normalized;
    }

    /**
     * @return array<mixed>
     */
    protected function normalizeToArray(mixed $value): array
    {
        return match (true) {
            $value instanceof Arrayable => $value->toArray(),
            $value instanceof JsonSerializable => (array) $value->jsonSerialize(),
            $value instanceof ArrayObject => $value->getArrayCopy(),
            is_array($value) => $value,
            default => [],
        };
    }

    protected function isDirectModelColumn(Model $model, string $column): bool
    {
        try {
            return $model->getConnection()
                ->getSchemaBuilder()
                ->hasColumn($model->getTable(), $column);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return class-string<JsonApiResource>|null
     */
    protected function inferRelatedResourceClass(Model $model): ?string
    {
        try {
            $resource = $model->toResource();
        } catch (LogicException) {
            return null;
        }

        return $resource::class;
    }

    /**
     * @return array<string, class-string>
     */
    protected function conventionalAdditionalFilters(Model $model): array
    {
        if (! in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            return [];
        }

        return [
            'with-trashed' => WithTrashed::class,
            'only-trashed' => OnlyTrashed::class,
        ];
    }
}
