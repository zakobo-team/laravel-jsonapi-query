<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Exceptions\InvalidFilterStructureException;
use Zakobo\JsonApiQuery\Exceptions\InvalidJsonApiQueryException;
use Zakobo\JsonApiQuery\Exceptions\InvalidSortParameterException;
use Zakobo\JsonApiQuery\Exceptions\UnknownFilterFieldException;
use Zakobo\JsonApiQuery\Exceptions\UnsupportedFilterFieldException;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostConventionalResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostResource;
use Zakobo\JsonApiQuery\Tests\TestCase;

class JsonApiCollectionMacroTest extends TestCase
{
    use RefreshDatabase;

    private function jsonApiData(string $resourceClass, Request $request): array
    {
        $result = Post::query()->jsonApiCollection($resourceClass, $request);
        $response = $result->toResponse($request);

        return json_decode($response->getContent(), true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function invalidQueryErrors(Request $request, string $resourceClass = PostResource::class): array
    {
        try {
            Post::query()->jsonApiCollection($resourceClass, $request);
        } catch (InvalidJsonApiQueryException $exception) {
            return $exception->errors();
        }

        $this->fail('Expected the query to be rejected.');
    }

    // --- Test 1: Basic collection ---

    #[Test]
    public function it_returns_a_json_api_resource_collection(): void
    {
        Post::create(['title' => 'First Post', 'slug' => 'first-post']);
        Post::create(['title' => 'Second Post', 'slug' => 'second-post']);

        $request = Request::create('/posts', 'GET');

        $data = $this->jsonApiData(PostResource::class, $request);

        $this->assertArrayHasKey('data', $data);
        $this->assertCount(2, $data['data']);
        $this->assertSame('posts', $data['data'][0]['type']);
    }

    // --- Test 2: Attribute filter ---

    #[Test]
    public function it_applies_attribute_filter(): void
    {
        Post::create(['title' => 'Hello World', 'slug' => 'hello']);
        Post::create(['title' => 'Goodbye World', 'slug' => 'goodbye']);

        $request = Request::create('/posts', 'GET', ['filter' => ['slug' => 'hello']]);

        $data = $this->jsonApiData(PostResource::class, $request);

        $this->assertCount(1, $data['data']);
        $this->assertSame('hello', $data['data'][0]['attributes']['slug']);
    }

    // --- Test 3: Operator filter ---

    #[Test]
    public function it_applies_operator_filter(): void
    {
        Post::create(['title' => 'Low', 'slug' => 'low', 'votes' => 50]);
        Post::create(['title' => 'High', 'slug' => 'high', 'votes' => 200]);

        $request = Request::create('/posts', 'GET', ['filter' => ['votes' => ['gt' => '100']]]);

        $data = $this->jsonApiData(PostResource::class, $request);

        $this->assertCount(1, $data['data']);
        $this->assertSame('high', $data['data'][0]['attributes']['slug']);
    }

    // --- Test 4: Has filter (scalar boolean) ---

    #[Test]
    public function it_applies_has_filter_when_value_is_scalar_boolean(): void
    {
        $postWithComments = Post::create(['title' => 'With', 'slug' => 'with']);
        Comment::create(['post_id' => $postWithComments->id, 'author' => 'Jane', 'body' => 'Nice']);

        Post::create(['title' => 'Without', 'slug' => 'without']);

        $request = Request::create('/posts', 'GET', ['filter' => ['comments' => 'true']]);

        $data = $this->jsonApiData(PostResource::class, $request);

        $this->assertCount(1, $data['data']);
        $this->assertSame('with', $data['data'][0]['attributes']['slug']);
    }

    // --- Test 5: WhereHas filter (array value) ---

    #[Test]
    public function it_applies_where_has_filter_when_value_is_array(): void
    {
        $post1 = Post::create(['title' => 'Post One', 'slug' => 'post-one']);
        Comment::create(['post_id' => $post1->id, 'author' => 'John', 'body' => 'Great']);

        $post2 = Post::create(['title' => 'Post Two', 'slug' => 'post-two']);
        Comment::create(['post_id' => $post2->id, 'author' => 'Jane', 'body' => 'Also great']);

        $request = Request::create('/posts', 'GET', ['filter' => ['comments.author' => 'John']]);

        $data = $this->jsonApiData(PostResource::class, $request);

        $this->assertCount(1, $data['data']);
        $this->assertSame('post-one', $data['data'][0]['attributes']['slug']);
    }

    // --- Test 6: Additional filter (WithTrashed) ---

    #[Test]
    public function it_applies_additional_filter(): void
    {
        $post = Post::create(['title' => 'Active', 'slug' => 'active']);
        $deleted = Post::create(['title' => 'Deleted', 'slug' => 'deleted']);
        $deleted->delete();

        $requestWithoutTrashed = Request::create('/posts', 'GET');
        $dataWithout = $this->jsonApiData(PostResource::class, $requestWithoutTrashed);
        $this->assertCount(1, $dataWithout['data']);

        $requestWithTrashed = Request::create('/posts', 'GET', ['filter' => ['with-trashed' => 'true']]);
        $dataWith = $this->jsonApiData(PostResource::class, $requestWithTrashed);
        $this->assertCount(2, $dataWith['data']);
    }

    #[Test]
    public function it_supports_soft_delete_filters_by_convention(): void
    {
        Post::create(['title' => 'Active', 'slug' => 'active']);
        $deleted = Post::create(['title' => 'Deleted', 'slug' => 'deleted']);
        $deleted->delete();

        $request = Request::create('/posts', 'GET', ['filter' => ['with-trashed' => 'true']]);

        $data = $this->jsonApiData(PostConventionalResource::class, $request);

        $this->assertCount(2, $data['data']);
    }

    // --- Test 7: Sorting ---

    #[Test]
    public function it_applies_sorting(): void
    {
        Post::create(['title' => 'Bravo', 'slug' => 'bravo']);
        Post::create(['title' => 'Alpha', 'slug' => 'alpha']);
        Post::create(['title' => 'Charlie', 'slug' => 'charlie']);

        $request = Request::create('/posts', 'GET', ['sort' => '-title']);

        $data = $this->jsonApiData(PostResource::class, $request);

        $titles = array_column(array_column($data['data'], 'attributes'), 'title');
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $titles);
    }

    // --- Test 8: Default sort ---

    #[Test]
    public function it_applies_default_sort_when_no_sort_param(): void
    {
        // PostResource has defaultSort = '-created_at'
        $first = Post::create(['title' => 'First', 'slug' => 'first']);
        // Ensure different created_at timestamps
        Post::where('id', $first->id)->update(['created_at' => now()->subMinute()]);

        $second = Post::create(['title' => 'Second', 'slug' => 'second']);

        $request = Request::create('/posts', 'GET');

        $data = $this->jsonApiData(PostResource::class, $request);

        $titles = array_column(array_column($data['data'], 'attributes'), 'title');
        $this->assertSame(['Second', 'First'], $titles);
    }

    // --- Test 9: Pagination ---

    #[Test]
    public function it_paginates_with_page_number_and_page_size(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Post::create([
                'title' => sprintf('Post %02d', $i),
                'slug' => sprintf('post-%02d', $i),
            ]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['number' => '2', 'size' => '3'], 'sort' => 'slug']);

        $data = $this->jsonApiData(PostResource::class, $request);

        $this->assertCount(3, $data['data']);
        $this->assertSame('Post 04', $data['data'][0]['attributes']['title']);
    }

    // --- Test 10: Per-resource default/max page size ---

    #[Test]
    public function it_respects_per_resource_default_page_size(): void
    {
        // PostResource has defaultPageSize = 15
        for ($i = 1; $i <= 20; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET');

        $data = $this->jsonApiData(PostResource::class, $request);

        $this->assertCount(15, $data['data']);
    }

    #[Test]
    public function it_respects_per_resource_max_page_size(): void
    {
        // PostResource has maxPageSize = 50
        for ($i = 1; $i <= 60; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['size' => '100']]);

        $data = $this->jsonApiData(PostResource::class, $request);

        $this->assertCount(50, $data['data']);
    }

    #[Test]
    public function it_uses_configured_default_page_size_when_resource_does_not_define_one(): void
    {
        config()->set('jsonapi-query.pagination.default_size', 7);

        for ($i = 1; $i <= 20; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET');

        $data = $this->jsonApiData(PostConventionalResource::class, $request);

        $this->assertCount(7, $data['data']);
    }

    #[Test]
    public function it_caps_page_size_at_configured_max_when_resource_does_not_define_one(): void
    {
        config()->set('jsonapi-query.pagination.max_size', 12);

        for ($i = 1; $i <= 20; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['size' => '50']]);

        $data = $this->jsonApiData(PostConventionalResource::class, $request);

        $this->assertCount(12, $data['data']);
    }

    // --- Test 11: excludedFromFilter ---

    #[Test]
    public function it_rejects_excluded_from_filter(): void
    {
        Post::create(['title' => 'Test', 'slug' => 'test']);

        $request = Request::create('/posts', 'GET', ['filter' => ['computed_score' => '10']]);

        try {
            Post::query()->jsonApiCollection(PostResource::class, $request);
            $this->fail('Expected the filter to be rejected.');
        } catch (UnsupportedFilterFieldException $exception) {
            $this->assertSame('filter[computed_score]', $exception->parameter());
        }
    }

    // --- Test 12: excludedFromSorting ---

    #[Test]
    public function it_rejects_excluded_from_sorting(): void
    {
        Post::create(['title' => 'Alpha', 'slug' => 'alpha']);
        Post::create(['title' => 'Bravo', 'slug' => 'bravo']);

        $request = Request::create('/posts', 'GET', ['sort' => 'computed_score']);

        try {
            Post::query()->jsonApiCollection(PostResource::class, $request);
            $this->fail('Expected the sort to be rejected.');
        } catch (InvalidSortParameterException $exception) {
            $this->assertSame('sort', $exception->parameter());
        }
    }

    // --- Test 13: Pre-existing query constraints ---

    #[Test]
    public function it_works_with_pre_existing_query_constraints(): void
    {
        Post::create(['title' => 'Published', 'slug' => 'published', 'published' => true]);
        Post::create(['title' => 'Draft', 'slug' => 'draft', 'published' => false]);

        $request = Request::create('/posts', 'GET');

        $result = Post::query()
            ->where('published', true)
            ->jsonApiCollection(PostResource::class, $request);

        $response = $result->toResponse($request);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']);
        $this->assertSame('published', $data['data'][0]['attributes']['slug']);
    }

    // --- Test 14: Empty results ---

    #[Test]
    public function it_returns_empty_data_array_when_no_results(): void
    {
        $request = Request::create('/posts', 'GET');

        $data = $this->jsonApiData(PostResource::class, $request);

        $this->assertSame([], $data['data']);
    }

    // --- Test 15: Rejects unknown filter keys ---

    #[Test]
    public function it_rejects_unknown_filter_keys(): void
    {
        Post::create(['title' => 'Test', 'slug' => 'test']);

        $request = Request::create('/posts', 'GET', ['filter' => ['nonexistent_field' => 'value']]);

        try {
            Post::query()->jsonApiCollection(PostResource::class, $request);
            $this->fail('Expected the filter to be rejected.');
        } catch (UnknownFilterFieldException $exception) {
            $this->assertSame('filter[nonexistent_field]', $exception->parameter());
        }
    }

    // --- Test 16: Rejects unknown sort fields ---

    #[Test]
    public function it_rejects_unknown_sort_fields(): void
    {
        Post::create(['title' => 'Alpha', 'slug' => 'alpha']);
        Post::create(['title' => 'Bravo', 'slug' => 'bravo']);

        $request = Request::create('/posts', 'GET', ['sort' => 'nonexistent_field']);
        $errors = $this->invalidQueryErrors($request);

        $this->assertSame('400', $errors[0]['status']);
        $this->assertSame('sort', $errors[0]['source']['parameter']);
    }

    // --- Test 17: Rejects non-array filter param ---

    #[Test]
    public function it_rejects_non_array_filter_param(): void
    {
        Post::create(['title' => 'Test', 'slug' => 'test']);

        $request = Request::create('/posts', 'GET', ['filter' => 'not-an-array']);

        try {
            Post::query()->jsonApiCollection(PostResource::class, $request);
            $this->fail('Expected the filter payload to be rejected.');
        } catch (InvalidFilterStructureException $exception) {
            $this->assertSame('filter', $exception->parameter());
        }
    }

    // --- Test 18: Ignores empty filter param ---

    #[Test]
    public function it_ignores_empty_filter_param(): void
    {
        Post::create(['title' => 'Test', 'slug' => 'test']);

        $request = Request::create('/posts', 'GET', ['filter' => []]);

        $data = $this->jsonApiData(PostResource::class, $request);

        $this->assertCount(1, $data['data']);
    }
}
