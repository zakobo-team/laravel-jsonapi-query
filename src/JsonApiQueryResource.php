<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

abstract class JsonApiQueryResource extends JsonApiResource
{
    /**
     * Attributes excluded from auto-generated filters.
     *
     * @var array<int, string>
     */
    public array $excludedFromFilter = [];

    /**
     * Attributes excluded from auto-generated sorting.
     *
     * @var array<int, string>
     */
    public array $excludedFromSorting = [];

    /**
     * Additional filters beyond auto-generated (scopes, soft deletes, custom).
     *
     * @var array<string, class-string>
     */
    public array $additionalFilters = [];

    /**
     * Area-aware query scopes.
     *
     * @var array<int, string>
     */
    public array $scopedBy = [];

    /**
     * Default sort when no ?sort= parameter is provided.
     */
    public ?string $defaultSort = null;

    /**
     * Default page size (per-resource override of global config).
     */
    public ?int $defaultPageSize = null;

    /**
     * Maximum page size (per-resource override of global config).
     */
    public ?int $maxPageSize = null;

    /**
     * Create an instance solely for reading query configuration.
     * Used by HandlesJsonApi trait to read properties without a model.
     */
    public static function queryConfig(): static
    {
        return new static(null);
    }

    /**
     * Get the attribute names that are filterable.
     * Returns $attributes minus $excludedFromFilter.
     *
     * @return array<int, string>
     */
    public function filterableAttributes(): array
    {
        return array_values(array_diff($this->attributes ?? [], $this->excludedFromFilter));
    }

    /**
     * Get the attribute names that are sortable.
     * Returns $attributes minus $excludedFromSorting.
     *
     * @return array<int, string>
     */
    public function sortableAttributes(): array
    {
        return array_values(array_diff($this->attributes ?? [], $this->excludedFromSorting));
    }

    /**
     * Get the relationship names that can be used for WhereHas filtering.
     *
     * @return array<int, string>
     */
    public function filterableRelationships(): array
    {
        return $this->relationships ?? [];
    }
}
