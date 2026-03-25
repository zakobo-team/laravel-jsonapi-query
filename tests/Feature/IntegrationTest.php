<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\User;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostResource;
use Zakobo\JsonApiQuery\Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    // --- Test 1: Filter + sort + paginate in one call ---

    #[Test]
    public function it_filters_sorts_and_paginates_in_one_call(): void
    {
        Post::create(['title' => 'Alpha', 'slug' => 'alpha', 'published' => true, 'votes' => 10]);
        Post::create(['title' => 'Bravo', 'slug' => 'bravo', 'published' => true, 'votes' => 50]);
        Post::create(['title' => 'Charlie', 'slug' => 'charlie', 'published' => true, 'votes' => 30]);
        Post::create(['title' => 'Delta', 'slug' => 'delta', 'published' => true, 'votes' => 70]);
        Post::create(['title' => 'Echo', 'slug' => 'echo', 'published' => false, 'votes' => 90]);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['published' => '1'],
            'sort' => '-votes',
            'page' => ['number' => '1', 'size' => '2'],
        ]);

        $result = Post::query()->jsonApiCollection(PostResource::class, $request);
        $response = $result->toResponse($request);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['data']);
        $this->assertSame('delta', $data['data'][0]['attributes']['slug']);
        $this->assertSame('bravo', $data['data'][1]['attributes']['slug']);

        // Verify pagination meta exists
        $this->assertArrayHasKey('meta', $data);
    }

    // --- Test 2: Soft delete lifecycle via macro ---

    #[Test]
    public function it_handles_soft_delete_lifecycle_via_macro(): void
    {
        $post = Post::create(['title' => 'Doomed', 'slug' => 'doomed']);
        Post::create(['title' => 'Survivor', 'slug' => 'survivor']);

        // Before deletion: both visible
        $requestBefore = Request::create('/posts', 'GET');
        $resultBefore = Post::query()->jsonApiCollection(PostResource::class, $requestBefore);
        $dataBefore = json_decode($resultBefore->toResponse($requestBefore)->getContent(), true);
        $this->assertCount(2, $dataBefore['data']);

        // Delete the post
        $post->delete();

        // After deletion: only survivor visible without with-trashed
        $requestAfter = Request::create('/posts', 'GET');
        $resultAfter = Post::query()->jsonApiCollection(PostResource::class, $requestAfter);
        $dataAfter = json_decode($resultAfter->toResponse($requestAfter)->getContent(), true);
        $this->assertCount(1, $dataAfter['data']);
        $this->assertSame('survivor', $dataAfter['data'][0]['attributes']['slug']);

        // With with-trashed: both visible again
        $requestTrashed = Request::create('/posts', 'GET', ['filter' => ['with-trashed' => 'true']]);
        $resultTrashed = Post::query()->jsonApiCollection(PostResource::class, $requestTrashed);
        $dataTrashed = json_decode($resultTrashed->toResponse($requestTrashed)->getContent(), true);
        $this->assertCount(2, $dataTrashed['data']);
    }

    // --- Test 3: Nested relationship filter via macro ---

    #[Test]
    public function it_filters_by_nested_relationship(): void
    {
        $post1 = Post::create(['title' => 'Post One', 'slug' => 'post-one']);
        Comment::create(['post_id' => $post1->id, 'author' => 'Alice', 'body' => 'Great post']);
        Comment::create(['post_id' => $post1->id, 'author' => 'Bob', 'body' => 'Agreed']);

        $post2 = Post::create(['title' => 'Post Two', 'slug' => 'post-two']);
        Comment::create(['post_id' => $post2->id, 'author' => 'Charlie', 'body' => 'Interesting']);

        $post3 = Post::create(['title' => 'Post Three', 'slug' => 'post-three']);
        // No comments

        // Filter for posts that have a comment by Alice
        $request = Request::create('/posts', 'GET', [
            'filter' => ['comments.author' => 'Alice'],
        ]);

        $result = Post::query()->jsonApiCollection(PostResource::class, $request);
        $data = json_decode($result->toResponse($request)->getContent(), true);

        $this->assertCount(1, $data['data']);
        $this->assertSame('post-one', $data['data'][0]['attributes']['slug']);
    }

    #[Test]
    public function it_requires_the_same_related_record_to_match_multiple_dot_filters(): void
    {
        $truePositive = Post::create(['title' => 'True Positive', 'slug' => 'true-positive']);
        Comment::create(['post_id' => $truePositive->id, 'author' => 'John', 'body' => 'Great']);

        $falsePositive = Post::create(['title' => 'False Positive', 'slug' => 'false-positive']);
        Comment::create(['post_id' => $falsePositive->id, 'author' => 'John', 'body' => 'Bad']);
        Comment::create(['post_id' => $falsePositive->id, 'author' => 'Jane', 'body' => 'Great']);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['comments.author' => 'John', 'comments.body' => 'Great'],
        ]);

        $result = Post::query()->jsonApiCollection(PostResource::class, $request);
        $data = json_decode($result->toResponse($request)->getContent(), true);

        $this->assertCount(1, $data['data']);
        $this->assertSame('true-positive', $data['data'][0]['attributes']['slug']);
    }

    // --- Test 4: Empty results return {"data": []} ---

    #[Test]
    public function it_returns_empty_data_array_for_no_results(): void
    {
        Post::create(['title' => 'Existing', 'slug' => 'existing', 'votes' => 5]);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['votes' => ['gt' => '999']],
        ]);

        $result = Post::query()->jsonApiCollection(PostResource::class, $request);
        $data = json_decode($result->toResponse($request)->getContent(), true);

        $this->assertSame([], $data['data']);
        $this->assertArrayHasKey('meta', $data);
    }

    // --- Test 5: Dot-notation relationship filter + relationship sort + pagination ---

    #[Test]
    public function it_filters_by_dot_notation_sorts_by_relationship_and_paginates(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $bob = User::create(['name' => 'Bob', 'email' => 'bob@test.com']);
        $charlie = User::create(['name' => 'Charlie', 'email' => 'charlie@test.com']);

        $post1 = Post::create(['title' => 'Charlie Post', 'slug' => 'charlie', 'user_id' => $charlie->id]);
        Comment::create(['post_id' => $post1->id, 'author' => 'X', 'body' => 'review']);

        $post2 = Post::create(['title' => 'Alice Post', 'slug' => 'alice', 'user_id' => $alice->id]);
        Comment::create(['post_id' => $post2->id, 'author' => 'Y', 'body' => 'review']);

        $post3 = Post::create(['title' => 'Bob Post', 'slug' => 'bob', 'user_id' => $bob->id]);
        Comment::create(['post_id' => $post3->id, 'author' => 'Z', 'body' => 'review']);

        // No comments on this one — filtered out by has comments
        Post::create(['title' => 'No Comments', 'slug' => 'none', 'user_id' => $alice->id]);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['comments' => 'true'],
            'sort' => 'user.name',
            'page' => ['number' => '1', 'size' => '2'],
        ]);

        $result = Post::query()->jsonApiCollection(PostResource::class, $request);
        $data = json_decode($result->toResponse($request)->getContent(), true);

        $this->assertCount(2, $data['data']);
        $this->assertSame('alice', $data['data'][0]['attributes']['slug']);
        $this->assertSame('bob', $data['data'][1]['attributes']['slug']);
        $this->assertSame(3, $data['meta']['total']);
    }

    // --- Test 6: Relationship sort (BelongsTo) + attribute sort combined ---

    #[Test]
    public function it_sorts_by_relationship_then_attribute(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $bob = User::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        Post::create(['title' => 'Bravo', 'slug' => 'b', 'user_id' => $alice->id]);
        Post::create(['title' => 'Alpha', 'slug' => 'a', 'user_id' => $alice->id]);
        Post::create(['title' => 'Charlie', 'slug' => 'c', 'user_id' => $bob->id]);

        $request = Request::create('/posts', 'GET', ['sort' => 'user.name,title']);

        $result = Post::query()->jsonApiCollection(PostResource::class, $request);
        $data = json_decode($result->toResponse($request)->getContent(), true);

        $slugs = array_column(array_column($data['data'], 'attributes'), 'slug');
        $this->assertSame(['a', 'b', 'c'], $slugs);
    }

    // --- Test 7: AdditionalSort (ScopeSort) + attribute filter ---

    #[Test]
    public function it_sorts_by_scope_sort_with_attribute_filter(): void
    {
        $post1 = Post::create(['title' => 'Post A', 'slug' => 'a', 'published' => true]);
        Comment::create(['post_id' => $post1->id, 'author' => 'X', 'body' => 'x', 'created_at' => '2024-06-01']);

        $post2 = Post::create(['title' => 'Post B', 'slug' => 'b', 'published' => true]);
        Comment::create(['post_id' => $post2->id, 'author' => 'Y', 'body' => 'y', 'created_at' => '2024-01-01']);

        $post3 = Post::create(['title' => 'Post C', 'slug' => 'c', 'published' => false]);
        Comment::create(['post_id' => $post3->id, 'author' => 'Z', 'body' => 'z', 'created_at' => '2024-12-01']);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['published' => '1'],
            'sort' => '-latest-comment',
        ]);

        $result = Post::query()->jsonApiCollection(PostResource::class, $request);
        $data = json_decode($result->toResponse($request)->getContent(), true);

        $this->assertCount(2, $data['data']);
        $this->assertSame('a', $data['data'][0]['attributes']['slug']);
        $this->assertSame('b', $data['data'][1]['attributes']['slug']);
    }

    // --- Test 8: includeFilter with dot-notation ---

    #[Test]
    public function it_applies_include_filter_with_dot_notation(): void
    {
        $post = Post::create(['title' => 'Test', 'slug' => 'test']);
        Comment::create(['post_id' => $post->id, 'author' => 'Alice', 'body' => 'First']);
        Comment::create(['post_id' => $post->id, 'author' => 'Bob', 'body' => 'Second']);

        $request = Request::create('/posts', 'GET', [
            'include' => 'comments',
            'includeFilter' => ['comments.author' => 'Alice'],
        ]);

        $result = Post::query()->jsonApiCollection(PostResource::class, $request);
        $loadedPost = $result->collection->first()->resource;

        $this->assertTrue($loadedPost->relationLoaded('comments'));
        $this->assertCount(1, $loadedPost->comments);
        $this->assertSame('Alice', $loadedPost->comments->first()->author);
    }
}
