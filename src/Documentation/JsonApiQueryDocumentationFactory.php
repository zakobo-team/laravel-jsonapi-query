<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Documentation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Zakobo\JsonApiQuery\Schema\RelationshipSchema;
use Zakobo\JsonApiQuery\Schema\ResourceSchema;
use Zakobo\JsonApiQuery\Schema\ResourceSchemaFactory;
use Zakobo\JsonApiQuery\Validation\QueryValidator;

final class JsonApiQueryDocumentationFactory
{
    public function __construct(
        private readonly ResourceSchemaFactory $resourceSchemas,
        private readonly QueryValidator $validator,
    ) {}

    /**
     * @param  class-string<JsonApiResource>|null  $resourceClass
     */
    public function fromBuilder(Builder $query, ?string $resourceClass = null, ?Request $request = null): JsonApiQueryDocumentation
    {
        return $this->for(
            $query->getModel(),
            $resourceClass,
            $request,
        );
    }

    /**
     * @param  class-string<JsonApiResource>|null  $resourceClass
     */
    public function for(Model $model, ?string $resourceClass = null, ?Request $request = null): JsonApiQueryDocumentation
    {
        $request = $this->jsonApiRequest($request);
        $schema = $this->resourceSchemas->fromModel($model, $request, $resourceClass);

        return new JsonApiQueryDocumentation(
            modelClass: $schema->modelClass,
            resourceClass: $schema->resourceClass,
            resourceType: $this->resourceType($schema, $request),
            filterFields: $this->filterFields($schema, $request),
            sortFields: $this->sortFields($schema, $request),
            includePaths: $this->includePaths($schema, $request),
            includeFilterFields: $this->includeFilterFields($schema, $request),
            fieldsets: $this->fieldsets($schema, $request),
            defaultPageSize: $schema->defaultPageSize ?? (int) config('jsonapi-query.pagination.default_size', 30),
            maxPageSize: $schema->maxPageSize ?? (int) config('jsonapi-query.pagination.max_size', 100),
            allowUnpaginated: $schema->allowUnpaginated,
        );
    }

    /**
     * @return list<string>
     */
    private function filterFields(ResourceSchema $schema, JsonApiRequest $request): array
    {
        return $this->unique([
            ...$this->validator->autoFilterableAttributes($schema),
            ...array_keys($schema->additionalFilters),
            ...array_keys($schema->relationships),
            ...$this->relationshipFilterFields($schema, $request),
        ]);
    }

    /**
     * @return list<string>
     */
    private function relationshipFilterFields(ResourceSchema $schema, JsonApiRequest $request, string $prefix = '', array $pathSegments = []): array
    {
        $fields = [];

        foreach ($schema->relationships as $relationshipName => $relationship) {
            if ($this->shouldSkipRelationship($relationshipName, $relationship, $pathSegments)) {
                continue;
            }

            $path = $prefix === '' ? $relationshipName : "{$prefix}.{$relationshipName}";
            $relatedSchema = $this->resourceSchemas->schemaForRelationship($relationship, $request);

            foreach ($this->validator->autoFilterableAttributes($relatedSchema) as $attribute) {
                $fields[] = "{$path}.{$attribute}";
            }

            $fields = [
                ...$fields,
                ...$this->relationshipFilterFields($relatedSchema, $request, $path, [...$pathSegments, $relationshipName]),
            ];
        }

        return $this->unique($fields);
    }

    /**
     * @return list<string>
     */
    private function sortFields(ResourceSchema $schema, JsonApiRequest $request): array
    {
        return $this->unique([
            ...$this->validator->autoSortableAttributes($schema),
            ...array_keys($schema->additionalSorts),
            ...$this->relationshipSortableFields($schema, $request),
        ]);
    }

    /**
     * @return list<string>
     */
    private function relationshipSortableFields(ResourceSchema $schema, JsonApiRequest $request): array
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $schema->modelClass;
        $model = new $modelClass;
        $fields = [];

        foreach ($schema->relationships as $relationshipName => $relationship) {
            if ($relationship->resourceClass === null) {
                continue;
            }

            $relation = Relation::noConstraints(fn () => $model->{$relationship->relationMethodName}());

            if (! $relation instanceof BelongsTo && ! $relation instanceof HasOne) {
                continue;
            }

            $relatedSchema = $this->resourceSchemas->schemaForRelationship($relationship, $request);

            foreach ($this->validator->autoSortableAttributes($relatedSchema) as $attribute) {
                $fields[] = "{$relationshipName}.{$attribute}";
            }
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function includePaths(ResourceSchema $schema, JsonApiRequest $request, string $prefix = '', array $pathSegments = []): array
    {
        $paths = [];

        foreach ($schema->relationships as $relationshipName => $relationship) {
            if ($this->shouldSkipRelationship($relationshipName, $relationship, $pathSegments)) {
                continue;
            }

            $path = $prefix === '' ? $relationshipName : "{$prefix}.{$relationshipName}";
            $paths[] = $path;

            $relatedSchema = $this->resourceSchemas->schemaForRelationship($relationship, $request);
            $paths = [
                ...$paths,
                ...$this->includePaths($relatedSchema, $request, $path, [...$pathSegments, $relationshipName]),
            ];
        }

        return $this->unique($paths);
    }

    /**
     * @return list<string>
     */
    private function includeFilterFields(ResourceSchema $schema, JsonApiRequest $request): array
    {
        $fields = [];

        foreach ($this->includePaths($schema, $request) as $path) {
            $relatedSchema = $this->schemaForPath($schema, $request, explode('.', $path));

            if ($relatedSchema === null) {
                continue;
            }

            foreach ($this->validator->autoFilterableAttributes($relatedSchema) as $attribute) {
                $fields[] = "{$path}.{$attribute}";
            }
        }

        return $this->unique($fields);
    }

    /**
     * @return array<string, list<string>>
     */
    private function fieldsets(ResourceSchema $schema, JsonApiRequest $request): array
    {
        $schemas = [$schema];

        foreach ($this->includePaths($schema, $request) as $path) {
            $relatedSchema = $this->schemaForPath($schema, $request, explode('.', $path));

            if ($relatedSchema !== null) {
                $schemas[] = $relatedSchema;
            }
        }

        $fieldsets = [];

        foreach ($schemas as $currentSchema) {
            $fieldsets[$this->resourceType($currentSchema, $request)] = array_keys($currentSchema->attributes);
        }

        return $fieldsets;
    }

    /**
     * @param  list<string>  $segments
     */
    private function schemaForPath(ResourceSchema $schema, JsonApiRequest $request, array $segments): ?ResourceSchema
    {
        $currentSchema = $schema;

        foreach ($segments as $segment) {
            $relationship = $currentSchema->relationship($segment);

            if ($relationship === null || $relationship->resourceClass === null) {
                return null;
            }

            $currentSchema = $this->resourceSchemas->schemaForRelationship($relationship, $request);
        }

        return $currentSchema;
    }

    /**
     * @param  array<int, string>  $pathSegments
     */
    private function shouldSkipRelationship(string $relationshipName, RelationshipSchema $relationship, array $pathSegments): bool
    {
        return $relationship->resourceClass === null
            || in_array($relationshipName, $pathSegments, true)
            || count($pathSegments) >= max(1, JsonApiResource::$maxRelationshipDepth);
    }

    private function resourceType(ResourceSchema $schema, JsonApiRequest $request): string
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $schema->modelClass;

        /** @var JsonApiResource $resource */
        $resource = $schema->resourceClass::make(new $modelClass);

        return $resource->resolveResourceType($request);
    }

    private function jsonApiRequest(?Request $request): JsonApiRequest
    {
        $request ??= Request::create('/');

        return $request instanceof JsonApiRequest
            ? $request
            : JsonApiRequest::createFrom($request);
    }

    /**
     * @param  array<int, string>  $values
     * @return list<string>
     */
    private function unique(array $values): array
    {
        return array_values(array_unique($values));
    }
}
