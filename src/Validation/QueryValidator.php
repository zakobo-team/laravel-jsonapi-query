<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Validation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Zakobo\JsonApiQuery\Exceptions\InvalidFilterStructureException;
use Zakobo\JsonApiQuery\Exceptions\InvalidIncludeFilterDependencyException;
use Zakobo\JsonApiQuery\Exceptions\InvalidIncludeFilterParameterException;
use Zakobo\JsonApiQuery\Exceptions\InvalidIncludeParameterException;
use Zakobo\JsonApiQuery\Exceptions\InvalidSortSyntaxException;
use Zakobo\JsonApiQuery\Exceptions\UnknownFilterFieldException;
use Zakobo\JsonApiQuery\Exceptions\UnknownIncludePathException;
use Zakobo\JsonApiQuery\Exceptions\UnknownSortFieldException;
use Zakobo\JsonApiQuery\Exceptions\UnsupportedFilterFieldException;
use Zakobo\JsonApiQuery\Exceptions\UnsupportedIncludeFilterException;
use Zakobo\JsonApiQuery\Exceptions\UnsupportedSortFieldException;
use Zakobo\JsonApiQuery\Schema\ResourceSchema;
use Zakobo\JsonApiQuery\Schema\ResourceSchemaFactory;

class QueryValidator
{
    protected const OPERATOR_KEYS = ['gt', 'gte', 'lt', 'lte', 'eq'];

    public function __construct(
        protected readonly ResourceSchemaFactory $resourceSchemas,
    ) {
    }

    public function validate(Builder $query, ResourceSchema $schema, JsonApiRequest $request): void
    {
        $this->validateFilters($schema, $request);
        $this->validateSort($query, $schema, $request);
        $this->validateIncludes($schema, $request);
    }

    /**
     * @return array<string>
     */
    public function autoFilterableAttributes(ResourceSchema $schema): array
    {
        return array_values(array_filter(
            array_keys($schema->attributes),
            fn (string $name) => $schema->hasAutoFilterableAttribute($name),
        ));
    }

    /**
     * @return array<string>
     */
    public function autoSortableAttributes(ResourceSchema $schema): array
    {
        return array_values(array_filter(
            array_keys($schema->attributes),
            fn (string $name) => $schema->hasAutoSortableAttribute($name),
        ));
    }

    /**
     * @return array<string>
     */
    public function relationshipSortableFields(
        ResourceSchema $schema,
        JsonApiRequest $request,
    ): array {
        $allowed = [];

        foreach ($schema->relationships as $relationshipName => $relationship) {
            if ($relationship->resourceClass === null) {
                continue;
            }

            $relatedSchema = $this->resourceSchemas->schemaForRelationship($relationship, $request);

            foreach ($relatedSchema->attributes as $attributeName => $attribute) {
                if ($relatedSchema->hasAutoSortableAttribute($attributeName)) {
                    $allowed[] = "{$relationshipName}.{$attributeName}";
                }
            }
        }

        return $allowed;
    }

    protected function validateFilters(ResourceSchema $schema, JsonApiRequest $request): void
    {
        $filters = $request->query('filter');

        if ($filters === null) {
            return;
        }

        if (! is_array($filters)) {
            throw new InvalidFilterStructureException('filter', 'The [filter] parameter must be an object.');
        }

        foreach ($filters as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidFilterStructureException('filter', 'Filter keys must be strings.');
            }

            $this->validateFilterEntry($schema, $request, $key, $value);
        }
    }

    protected function validateFilterEntry(
        ResourceSchema $schema,
        JsonApiRequest $request,
        string $key,
        mixed $value,
        string $parameter = '',
    ): void {
        $parameter = $parameter !== '' ? $parameter : "filter[{$key}]";

        if (array_key_exists($key, $schema->additionalFilters)) {
            return;
        }

        if ($schema->hasAutoFilterableAttribute($key)) {
            $this->validateAttributeFilterValue($parameter, $value);

            return;
        }

        if ($schema->attribute($key) !== null) {
            throw new UnsupportedFilterFieldException($parameter, "Filtering by [{$key}] is not supported.");
        }

        if ($schema->relationship($key) !== null) {
            $this->validateRelationshipFilterValue($schema, $request, $key, $value, $parameter);

            return;
        }

        if (str_contains($key, '.')) {
            $this->validateDottedRelationshipFilter($schema, $request, $key, $value, $parameter);

            return;
        }

        throw new UnknownFilterFieldException($parameter, "Unknown filter field [{$key}].");
    }

    protected function validateRelationshipFilterValue(
        ResourceSchema $schema,
        JsonApiRequest $request,
        string $relationshipName,
        mixed $value,
        string $parameter,
    ): void {
        if (! is_array($value)) {
            return;
        }

        $relationship = $schema->relationship($relationshipName);

        if ($relationship === null || $relationship->resourceClass === null) {
            throw new UnsupportedFilterFieldException($parameter, "Relationship filter [{$relationshipName}] cannot be traversed.");
        }

        $relatedSchema = $this->resourceSchemas->schemaForRelationship($relationship, $request);
        $this->validateNestedFilterArray($relatedSchema, $request, $value, $parameter);
    }

    protected function validateNestedFilterArray(
        ResourceSchema $schema,
        JsonApiRequest $request,
        array $filters,
        string $parameterPrefix,
    ): void {
        foreach ($filters as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidFilterStructureException($parameterPrefix, 'Nested filter keys must be strings.');
            }

            $this->validateFilterEntry(
                $schema,
                $request,
                $key,
                $value,
                "{$parameterPrefix}[{$key}]",
            );
        }
    }

    protected function validateDottedRelationshipFilter(
        ResourceSchema $schema,
        JsonApiRequest $request,
        string $key,
        mixed $value,
        string $parameter,
    ): void {
        $segments = explode('.', $key);
        $currentSchema = $schema;

        while (count($segments) > 1) {
            $relationshipName = array_shift($segments);
            $relationship = $relationshipName !== null
                ? $currentSchema->relationship($relationshipName)
                : null;

            if ($relationship === null || $relationship->resourceClass === null) {
                throw new UnknownFilterFieldException($parameter, "Unknown relationship path [{$key}].");
            }

            $currentSchema = $this->resourceSchemas->schemaForRelationship($relationship, $request);
        }

        $leaf = $segments[0];

        if ($currentSchema->hasAutoFilterableAttribute($leaf)) {
            $this->validateAttributeFilterValue($parameter, $value);

            return;
        }

        if ($currentSchema->attribute($leaf) !== null) {
            throw new UnsupportedFilterFieldException($parameter, "Filtering by [{$key}] is not supported.");
        }

        if ($currentSchema->relationship($leaf) !== null && ! is_array($value)) {
            return;
        }

        throw new UnknownFilterFieldException($parameter, "Unknown filter field [{$key}].");
    }

    protected function validateAttributeFilterValue(string $parameter, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        if (! $this->containsOperatorKeys($value)) {
            throw new InvalidFilterStructureException($parameter, 'Attribute filters only support scalar values or operator objects.');
        }

        foreach (array_keys($value) as $operator) {
            if (! in_array($operator, self::OPERATOR_KEYS, true)) {
                throw new InvalidFilterStructureException($parameter, "Unsupported filter operator [{$operator}].");
            }
        }
    }

    protected function validateSort(
        Builder $query,
        ResourceSchema $schema,
        JsonApiRequest $request,
    ): void {
        $sort = $request->query('sort');

        if ($sort === null || $sort === '') {
            return;
        }

        if (! is_string($sort)) {
            throw new InvalidSortSyntaxException('sort', 'The [sort] parameter must be a comma-separated string.');
        }

        foreach (explode(',', $sort) as $field) {
            $field = trim($field);

            if ($field === '' || $field === '-') {
                throw new InvalidSortSyntaxException('sort', 'Invalid sort field.');
            }

            if (str_starts_with($field, '-')) {
                $field = substr($field, 1);
            }

            if ($field === '') {
                throw new InvalidSortSyntaxException('sort', 'Invalid sort field.');
            }

            $this->validateSortField($query, $schema, $request, $field);
        }
    }

    protected function validateSortField(
        Builder $query,
        ResourceSchema $schema,
        JsonApiRequest $request,
        string $field,
    ): void {
        if (array_key_exists($field, $schema->additionalSorts)) {
            return;
        }

        if ($schema->hasAutoSortableAttribute($field)) {
            return;
        }

        if ($schema->attribute($field) !== null) {
            throw new UnsupportedSortFieldException('sort', "Sorting by [{$field}] is not supported.");
        }

        if (! str_contains($field, '.')) {
            throw new UnknownSortFieldException('sort', "Unknown sort field [{$field}].");
        }

        $parts = explode('.', $field);

        if (count($parts) !== 2) {
            throw new UnsupportedSortFieldException('sort', "Sorting by [{$field}] is not supported.");
        }

        [$relationshipName, $attributeName] = $parts;

        $relationship = $schema->relationship($relationshipName);

        if ($relationship === null || $relationship->resourceClass === null) {
            throw new UnknownSortFieldException('sort', "Unknown sort field [{$field}].");
        }

        $relatedSchema = $this->resourceSchemas->schemaForRelationship($relationship, $request);

        if (! $relatedSchema->hasAutoSortableAttribute($attributeName)) {
            throw new UnsupportedSortFieldException('sort', "Sorting by [{$field}] is not supported.");
        }

        $model = $query->getModel();

        $relation = Relation::noConstraints(fn () => $model->{$relationship->relationMethodName}());

        if (! $relation instanceof BelongsTo && ! $relation instanceof HasOne) {
            throw new UnsupportedSortFieldException('sort', "Sorting by [{$field}] is not supported.");
        }
    }

    protected function validateIncludes(ResourceSchema $schema, JsonApiRequest $request): void
    {
        $include = $request->query('include');

        if ($include !== null && ! is_string($include)) {
            throw new InvalidIncludeParameterException('include', 'The [include] parameter must be a comma-separated string.');
        }

        foreach ($this->requestedIncludePaths($request) as $path) {
            $this->validateIncludePath($schema, $request, $path);
        }

        $includeFilters = $request->query('includeFilter');

        if ($includeFilters === null) {
            return;
        }

        if (! is_array($includeFilters)) {
            throw new InvalidIncludeFilterParameterException('includeFilter', 'The [includeFilter] parameter must be an object.');
        }

        $requestedPaths = $this->requestedIncludePaths($request);

        foreach ($includeFilters as $key => $value) {
            if (! is_string($key) || ! str_contains($key, '.')) {
                throw new InvalidIncludeFilterParameterException('includeFilter', 'Include filters must target [relationship.attribute] paths.');
            }

            $segments = explode('.', $key);
            $attribute = array_pop($segments);
            $relationshipPath = implode('.', $segments);

            if (! in_array($relationshipPath, $requestedPaths, true)) {
                throw new InvalidIncludeFilterDependencyException(
                    "includeFilter[{$key}]",
                    "Include filter [{$key}] requires [{$relationshipPath}] to be requested in [include].",
                );
            }

            $relatedSchema = $this->schemaForPath($schema, $request, $segments);

            if ($relatedSchema === null || ! $relatedSchema->hasAutoFilterableAttribute($attribute)) {
                throw new UnsupportedIncludeFilterException(
                    "includeFilter[{$key}]",
                    "Filtering includes by [{$key}] is not supported.",
                );
            }

            $this->validateAttributeFilterValue("includeFilter[{$key}]", $value);
        }
    }

    protected function validateIncludePath(
        ResourceSchema $schema,
        JsonApiRequest $request,
        string $path,
    ): void {
        $segments = explode('.', $path);
        $currentSchema = $schema;

        foreach ($segments as $segment) {
            $relationship = $currentSchema->relationship($segment);

            if ($relationship === null || $relationship->resourceClass === null) {
                throw new UnknownIncludePathException('include', "Unknown include path [{$path}].");
            }

            $currentSchema = $this->resourceSchemas->schemaForRelationship($relationship, $request);
        }
    }

    /**
     * @param  array<int, string>  $segments
     */
    protected function schemaForPath(
        ResourceSchema $schema,
        JsonApiRequest $request,
        array $segments,
    ): ?ResourceSchema {
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
