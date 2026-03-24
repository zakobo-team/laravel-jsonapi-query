<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Filters\Scope;
use Zakobo\JsonApiQuery\Filters\WithTrashed;
use Zakobo\JsonApiQuery\JsonApiQueryResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostResource;
use Zakobo\JsonApiQuery\Tests\TestCase;

class JsonApiQueryResourceTest extends TestCase
{
    // =========================================================================
    // Happy path: queryConfig() and property access
    // =========================================================================

    #[Test]
    public function query_config_returns_instance_with_all_properties_readable(): void
    {
        $config = PostResource::queryConfig();

        $this->assertInstanceOf(PostResource::class, $config);
        $this->assertInstanceOf(JsonApiQueryResource::class, $config);
        $this->assertSame(['title', 'slug', 'votes', 'published'], $config->attributes);
        $this->assertSame(['comments', 'tags', 'user', 'meta'], $config->relationships);
    }

    #[Test]
    public function filterable_attributes_returns_attributes_minus_excluded_from_filter(): void
    {
        $config = PostResource::queryConfig();

        $this->assertSame(['title', 'slug', 'votes', 'published'], $config->filterableAttributes());
    }

    #[Test]
    public function sortable_attributes_returns_attributes_minus_excluded_from_sorting(): void
    {
        $config = PostResource::queryConfig();

        $this->assertSame(['title', 'slug', 'votes', 'published'], $config->sortableAttributes());
    }

    #[Test]
    public function filterable_relationships_returns_relationships(): void
    {
        $config = PostResource::queryConfig();

        $this->assertSame(['comments', 'tags', 'user', 'meta'], $config->filterableRelationships());
    }

    #[Test]
    public function additional_filters_property_is_accessible(): void
    {
        $config = PostResource::queryConfig();

        $this->assertArrayHasKey('with-trashed', $config->additionalFilters);
        $this->assertArrayHasKey('popular', $config->additionalFilters);
        $this->assertSame(WithTrashed::class, $config->additionalFilters['with-trashed']);
        $this->assertSame(Scope::class, $config->additionalFilters['popular']);
    }

    #[Test]
    public function scoped_by_property_is_accessible(): void
    {
        $config = PostResource::queryConfig();

        $this->assertSame([], $config->scopedBy);
    }

    #[Test]
    public function default_sort_property_is_accessible(): void
    {
        $config = PostResource::queryConfig();

        $this->assertSame('-created_at', $config->defaultSort);
    }

    #[Test]
    public function pagination_properties_are_accessible(): void
    {
        $config = PostResource::queryConfig();

        $this->assertSame(15, $config->defaultPageSize);
        $this->assertSame(50, $config->maxPageSize);
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    #[Test]
    public function resource_with_no_exclusions_has_all_attributes_filterable_and_sortable(): void
    {
        $resource = new class(null) extends JsonApiQueryResource
        {
            public $attributes = ['name', 'email', 'age'];

            public $relationships = ['posts'];
        };

        $this->assertSame(['name', 'email', 'age'], $resource->filterableAttributes());
        $this->assertSame(['name', 'email', 'age'], $resource->sortableAttributes());
    }

    #[Test]
    public function resource_with_empty_attributes_returns_empty_filterable_array(): void
    {
        $resource = new class(null) extends JsonApiQueryResource
        {
            public $attributes = [];
        };

        $this->assertSame([], $resource->filterableAttributes());
        $this->assertSame([], $resource->sortableAttributes());
    }

    #[Test]
    public function resource_with_null_default_sort_returns_null(): void
    {
        $resource = new class(null) extends JsonApiQueryResource
        {
        };

        $this->assertNull($resource->defaultSort);
    }

    #[Test]
    public function resource_with_null_pagination_returns_null(): void
    {
        $resource = new class(null) extends JsonApiQueryResource
        {
        };

        $this->assertNull($resource->defaultPageSize);
        $this->assertNull($resource->maxPageSize);
    }

    #[Test]
    public function excluding_a_non_existent_attribute_does_not_cause_error(): void
    {
        $resource = new class(null) extends JsonApiQueryResource
        {
            public $attributes = ['title', 'slug'];

            public array $excludedFromFilter = ['non_existent_field'];

            public array $excludedFromSorting = ['another_non_existent'];
        };

        $this->assertSame(['title', 'slug'], $resource->filterableAttributes());
        $this->assertSame(['title', 'slug'], $resource->sortableAttributes());
    }

    #[Test]
    public function query_config_works_without_a_model(): void
    {
        $config = PostResource::queryConfig();

        // Should not throw — resource wraps null
        $this->assertSame(['title', 'slug', 'votes', 'published'], $config->filterableAttributes());
        $this->assertSame(['title', 'slug', 'votes', 'published'], $config->sortableAttributes());
        $this->assertSame(['comments', 'tags', 'user', 'meta'], $config->filterableRelationships());
    }

    #[Test]
    public function filterable_relationships_returns_empty_array_when_no_relationships_defined(): void
    {
        $resource = new class(null) extends JsonApiQueryResource
        {
            public $attributes = ['name'];
        };

        $this->assertSame([], $resource->filterableRelationships());
    }
}
