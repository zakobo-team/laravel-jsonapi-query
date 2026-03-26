<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\QueryConfig;

trait HasJsonApiQueryConfiguration
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
     * Additional sorts beyond auto-generated (scopes, custom).
     *
     * @var array<string, class-string>
     */
    public array $additionalSorts = [];

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

    public function jsonApiQueryConfiguration(): array
    {
        return [
            'excluded_from_filter' => $this->excludedFromFilter,
            'excluded_from_sorting' => $this->excludedFromSorting,
            'additional_filters' => $this->additionalFilters,
            'additional_sorts' => $this->additionalSorts,
            'default_sort' => $this->defaultSort,
            'default_page_size' => $this->defaultPageSize,
            'max_page_size' => $this->maxPageSize,
        ];
    }
}
