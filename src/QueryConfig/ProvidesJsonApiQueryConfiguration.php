<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\QueryConfig;

interface ProvidesJsonApiQueryConfiguration
{
    /**
     * Return query-specific configuration that augments a plain JsonApiResource.
     *
     * @return array{
     *   excluded_from_filter?: array<int, string>,
     *   excluded_from_sorting?: array<int, string>,
     *   additional_filters?: array<string, class-string>,
     *   additional_sorts?: array<string, class-string>,
     *   default_sort?: string|null,
     *   default_page_size?: int|null,
     *   max_page_size?: int|null
     * }
     */
    public function jsonApiQueryConfiguration(): array;
}
