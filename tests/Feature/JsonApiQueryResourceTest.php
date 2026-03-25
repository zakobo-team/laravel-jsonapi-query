<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Filters\Scope;
use Zakobo\JsonApiQuery\JsonApiQueryResource;
use Zakobo\JsonApiQuery\Sorting\ScopeSort;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostResource;
use Zakobo\JsonApiQuery\Tests\TestCase;

class JsonApiQueryResourceTest extends TestCase
{
    #[Test]
    public function query_config_returns_instance_with_properties_readable(): void
    {
        $config = PostResource::queryConfig();

        $this->assertInstanceOf(PostResource::class, $config);
        $this->assertInstanceOf(JsonApiQueryResource::class, $config);
        $this->assertSame('-created_at', $config->defaultSort);
        $this->assertSame(15, $config->defaultPageSize);
        $this->assertSame(50, $config->maxPageSize);
    }

    #[Test]
    public function filterable_attributes_are_derived_from_to_attributes(): void
    {
        $config = PostResource::queryConfig();

        $this->assertSame(['title', 'slug', 'votes', 'published', 'created_at'], $config->filterableAttributes());
    }

    #[Test]
    public function sortable_attributes_are_derived_from_to_attributes(): void
    {
        $config = PostResource::queryConfig();

        $this->assertSame(['title', 'slug', 'votes', 'published', 'created_at'], $config->sortableAttributes());
    }

    #[Test]
    public function filterable_relationships_are_derived_from_to_relationships(): void
    {
        $config = PostResource::queryConfig();

        $this->assertSame(['comments', 'tags', 'user', 'meta'], $config->filterableRelationships());
    }

    #[Test]
    public function additional_filters_property_is_accessible(): void
    {
        $config = PostResource::queryConfig();

        $this->assertSame(Scope::class, $config->additionalFilters['popular']);
    }

    #[Test]
    public function additional_sorts_property_is_accessible(): void
    {
        $config = PostResource::queryConfig();

        $this->assertSame(ScopeSort::class, $config->additionalSorts['latest-comment']);
    }

    #[Test]
    public function resource_with_no_exclusions_includes_indexed_and_keyed_attribute_names(): void
    {
        $resource = new class(null) extends JsonApiQueryResource
        {
            public function toAttributes(Request $request): array
            {
                return [
                    'name',
                    'email_alias' => fn () => 'derived',
                    'age',
                ];
            }
        };

        $this->assertSame(['name', 'email_alias', 'age'], $resource->filterableAttributes());
        $this->assertSame(['name', 'email_alias', 'age'], $resource->sortableAttributes());
    }

    #[Test]
    public function resource_with_empty_attributes_returns_empty_arrays(): void
    {
        $resource = new class(null) extends JsonApiQueryResource
        {
            public function toAttributes(Request $request): array
            {
                return [];
            }
        };

        $this->assertSame([], $resource->filterableAttributes());
        $this->assertSame([], $resource->sortableAttributes());
    }

    #[Test]
    public function resource_with_null_defaults_returns_null(): void
    {
        $resource = new class(null) extends JsonApiQueryResource
        {
        };

        $this->assertNull($resource->defaultSort);
        $this->assertNull($resource->defaultPageSize);
        $this->assertNull($resource->maxPageSize);
    }

    #[Test]
    public function excluding_non_existent_attribute_does_not_fail(): void
    {
        $resource = new class(null) extends JsonApiQueryResource
        {
            public array $excludedFromFilter = ['missing'];

            public array $excludedFromSorting = ['also_missing'];

            public function toAttributes(Request $request): array
            {
                return ['title', 'slug'];
            }
        };

        $this->assertSame(['title', 'slug'], $resource->filterableAttributes());
        $this->assertSame(['title', 'slug'], $resource->sortableAttributes());
    }

    #[Test]
    public function filterable_relationships_returns_empty_array_when_no_relationships_defined(): void
    {
        $resource = new class(null) extends JsonApiQueryResource
        {
            public function toAttributes(Request $request): array
            {
                return ['name'];
            }
        };

        $this->assertSame([], $resource->filterableRelationships());
    }
}
