<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Exceptions\InvalidIncludeFilterDependencyException;
use Zakobo\JsonApiQuery\Exceptions\InvalidJsonApiQueryException;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostResource;
use Zakobo\JsonApiQuery\Tests\TestCase;

class IncludeFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<string, mixed>>
     */
    private function invalidQueryErrors(Request $request): array
    {
        try {
            Post::query()->jsonApiCollection(PostResource::class, $request);
        } catch (InvalidJsonApiQueryException $exception) {
            return $exception->errors();
        }

        $this->fail('Expected the query to be rejected.');
    }

    // --- Test 1: includeFilter constrains loaded relationship ---

    #[Test]
    public function it_constrains_loaded_relationship_via_include_filter(): void
    {
        $post = Post::create(['title' => 'Test', 'slug' => 'test']);
        Comment::create(['post_id' => $post->id, 'author' => 'John', 'body' => 'Hello']);
        Comment::create(['post_id' => $post->id, 'author' => 'Jane', 'body' => 'World']);

        $request = Request::create('/posts', 'GET', [
            'include' => 'comments',
            'includeFilter' => ['comments.author' => 'John'],
        ]);

        $result = Post::query()->jsonApiCollection(PostResource::class, $request);
        $response = $result->toResponse($request);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']);

        // The post's loaded comments should be constrained to only John's
        $loadedPost = $result->collection->first()->resource;
        $this->assertTrue($loadedPost->relationLoaded('comments'));
        $this->assertCount(1, $loadedPost->comments);
        $this->assertSame('John', $loadedPost->comments->first()->author);
    }

    // --- Test 2: Primary data is NOT filtered by includeFilter ---

    #[Test]
    public function it_does_not_filter_primary_data_with_include_filter(): void
    {
        $post1 = Post::create(['title' => 'Post One', 'slug' => 'post-one']);
        Comment::create(['post_id' => $post1->id, 'author' => 'John', 'body' => 'Hello']);

        $post2 = Post::create(['title' => 'Post Two', 'slug' => 'post-two']);
        Comment::create(['post_id' => $post2->id, 'author' => 'Jane', 'body' => 'World']);

        $request = Request::create('/posts', 'GET', [
            'include' => 'comments',
            'includeFilter' => ['comments.author' => 'John'],
        ]);

        $result = Post::query()->jsonApiCollection(PostResource::class, $request);
        $response = $result->toResponse($request);
        $data = json_decode($response->getContent(), true);

        // Both posts should be returned (primary data is not filtered)
        $this->assertCount(2, $data['data']);
    }

    // --- Test 3: Combined filter + includeFilter apply independently ---

    #[Test]
    public function it_applies_filter_and_include_filter_independently(): void
    {
        $post1 = Post::create(['title' => 'Alpha', 'slug' => 'alpha']);
        Comment::create(['post_id' => $post1->id, 'author' => 'John', 'body' => 'A']);
        Comment::create(['post_id' => $post1->id, 'author' => 'Jane', 'body' => 'B']);

        $post2 = Post::create(['title' => 'Bravo', 'slug' => 'bravo']);
        Comment::create(['post_id' => $post2->id, 'author' => 'John', 'body' => 'C']);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['slug' => 'alpha'],
            'include' => 'comments',
            'includeFilter' => ['comments.author' => 'John'],
        ]);

        $result = Post::query()->jsonApiCollection(PostResource::class, $request);
        $response = $result->toResponse($request);
        $data = json_decode($response->getContent(), true);

        // Primary data filtered to only 'alpha'
        $this->assertCount(1, $data['data']);
        $this->assertSame('alpha', $data['data'][0]['attributes']['slug']);

        // Loaded comments constrained to only John's
        $loadedPost = $result->collection->first()->resource;
        $this->assertCount(1, $loadedPost->comments);
        $this->assertSame('John', $loadedPost->comments->first()->author);
    }

    // --- Test 4: includeFilter on non-included relationship is rejected ---

    #[Test]
    public function it_rejects_include_filter_on_non_included_relationship(): void
    {
        $post = Post::create(['title' => 'Test', 'slug' => 'test']);
        Comment::create(['post_id' => $post->id, 'author' => 'John', 'body' => 'Hello']);

        $request = Request::create('/posts', 'GET', [
            'includeFilter' => ['comments.author' => 'John'],
        ]);

        try {
            Post::query()->jsonApiCollection(PostResource::class, $request);
            $this->fail('Expected the include filter to be rejected.');
        } catch (InvalidIncludeFilterDependencyException $exception) {
            $this->assertSame('includeFilter[comments.author]', $exception->parameter());
            $this->assertStringContainsString('requires [comments] to be requested in [include]', $exception->detail());
        }
    }

    // --- Test 5: includeFilter with operators ---

    #[Test]
    public function it_applies_include_filter_with_operators(): void
    {
        $post = Post::create(['title' => 'Test', 'slug' => 'test']);
        Comment::create(['post_id' => $post->id, 'author' => 'Alice', 'body' => 'Old', 'created_at' => '2024-01-01 00:00:00']);
        Comment::create(['post_id' => $post->id, 'author' => 'Bob', 'body' => 'New', 'created_at' => '2024-06-01 00:00:00']);
        Comment::create(['post_id' => $post->id, 'author' => 'Charlie', 'body' => 'Newest', 'created_at' => '2024-12-01 00:00:00']);

        $request = Request::create('/posts', 'GET', [
            'include' => 'comments',
            'includeFilter' => ['comments.created_at' => ['gt' => '2024-03-01 00:00:00']],
        ]);

        $result = Post::query()->jsonApiCollection(PostResource::class, $request);
        $response = $result->toResponse($request);
        $data = json_decode($response->getContent(), true);

        $loadedPost = $result->collection->first()->resource;
        $this->assertTrue($loadedPost->relationLoaded('comments'));
        // Only comments after 2024-03-01 should be loaded
        foreach ($loadedPost->comments as $comment) {
            $this->assertTrue($comment->created_at->gt('2024-03-01 00:00:00'));
        }
    }

    // --- Test 6: Empty includeFilter param is ignored ---

    #[Test]
    public function it_ignores_empty_include_filter_param(): void
    {
        Post::create(['title' => 'Test', 'slug' => 'test']);

        $request = Request::create('/posts', 'GET', [
            'include' => 'comments',
            'includeFilter' => [],
        ]);

        $result = Post::query()->jsonApiCollection(PostResource::class, $request);
        $response = $result->toResponse($request);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']);
    }

    // --- Test 7: includeFilter without include param entirely is rejected ---

    #[Test]
    public function it_rejects_include_filter_without_include_param(): void
    {
        $post = Post::create(['title' => 'Test', 'slug' => 'test']);
        Comment::create(['post_id' => $post->id, 'author' => 'John', 'body' => 'Hello']);

        $request = Request::create('/posts', 'GET', [
            'includeFilter' => ['comments.author' => 'John'],
        ]);

        $errors = $this->invalidQueryErrors($request);

        $this->assertSame('400', $errors[0]['status']);
        $this->assertSame('includeFilter[comments.author]', $errors[0]['source']['parameter']);
    }
}
