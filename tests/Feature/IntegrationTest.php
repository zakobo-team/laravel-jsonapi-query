<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Controller;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Http\Concerns\HandlesJsonApiIndex;
use Zakobo\JsonApiQuery\Http\Contracts\JsonApiController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Controllers\PostCommentsController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Controllers\PostController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\ScopedPostResource;
use Zakobo\JsonApiQuery\Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        $router->get('/api/posts', [PostController::class, 'index']);
        $router->get('/api/posts/{post}', [PostController::class, 'show']);
        $router->post('/api/posts', [PostController::class, 'store']);
        $router->patch('/api/posts/{post}', [PostController::class, 'update']);
        $router->delete('/api/posts/{post}', [PostController::class, 'destroy']);
        $router->get('/api/posts/{post}/comments', [PostCommentsController::class, 'index']);
        $router->get('/api/scoped-posts', [IntegrationScopedPostController::class, 'index']);
    }

    // =========================================================================
    // 1. Filter + sort + paginate in one request
    // =========================================================================

    #[Test]
    public function it_combines_filter_sort_and_paginate_in_one_request(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            Post::create([
                'title' => "Published Post {$i}",
                'slug' => "published-{$i}",
                'published' => true,
            ]);
        }

        for ($i = 1; $i <= 8; $i++) {
            Post::create([
                'title' => "Draft Post {$i}",
                'slug' => "draft-{$i}",
                'published' => false,
            ]);
        }

        $response = $this->getJson('/api/posts?filter[published]=1&sort=-title&page[number]=1&page[size]=5');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');

        // Sorted descending by title: "Published Post 9" comes before "Published Post 8" (string sort)
        $titles = array_map(
            fn ($item) => $item['attributes']['title'],
            $response->json('data'),
        );
        $sortedTitles = $titles;
        usort($sortedTitles, fn ($a, $b) => strcmp($b, $a));
        $this->assertSame($sortedTitles, $titles);

        // All returned posts are published
        foreach ($response->json('data') as $item) {
            $this->assertSame(1, $item['attributes']['published']);
        }

        // Pagination meta shows correct total (12 published posts)
        $response->assertJsonPath('meta.total', 12);
        $response->assertJsonPath('meta.current_page', 1);
    }

    // =========================================================================
    // 2. Soft delete lifecycle
    // =========================================================================

    #[Test]
    public function it_handles_soft_delete_lifecycle(): void
    {
        $post = Post::create(['title' => 'Lifecycle Post', 'slug' => 'lifecycle']);

        // Verify post appears in listing
        $response = $this->getJson('/api/posts');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.title', 'Lifecycle Post');

        // Soft delete the post
        $this->deleteJson("/api/posts/{$post->id}")->assertNoContent();

        // Verify post no longer appears in listing
        $response = $this->getJson('/api/posts');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');

        // Verify post appears when with-trashed filter is used
        $response = $this->getJson('/api/posts?filter[with-trashed]=true');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.title', 'Lifecycle Post');
    }

    // =========================================================================
    // 3. Full CRUD lifecycle
    // =========================================================================

    #[Test]
    public function it_handles_full_crud_lifecycle(): void
    {
        // CREATE
        $createResponse = $this->postJson('/api/posts', [
            'data' => [
                'type' => 'posts',
                'attributes' => [
                    'title' => 'Integration Post',
                    'slug' => 'integration-post',
                    'votes' => 5,
                    'published' => false,
                ],
            ],
        ]);

        $createResponse->assertStatus(201);
        $postId = $createResponse->json('data.id');
        $this->assertNotNull($postId);

        // READ (show) - verify created data
        $showResponse = $this->getJson("/api/posts/{$postId}");
        $showResponse->assertOk();
        $showResponse->assertJsonPath('data.type', 'posts');
        $showResponse->assertJsonPath('data.id', (string) $postId);
        $showResponse->assertJsonPath('data.attributes.title', 'Integration Post');
        $showResponse->assertJsonPath('data.attributes.slug', 'integration-post');
        $showResponse->assertJsonPath('data.attributes.votes', 5);

        // UPDATE
        $updateResponse = $this->patchJson("/api/posts/{$postId}", [
            'data' => [
                'type' => 'posts',
                'id' => (string) $postId,
                'attributes' => [
                    'title' => 'Updated Integration Post',
                    'votes' => 42,
                ],
            ],
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.attributes.title', 'Updated Integration Post');

        // READ again - verify update persisted
        $showResponse = $this->getJson("/api/posts/{$postId}");
        $showResponse->assertOk();
        $showResponse->assertJsonPath('data.attributes.title', 'Updated Integration Post');
        $showResponse->assertJsonPath('data.attributes.votes', 42);
        $showResponse->assertJsonPath('data.attributes.slug', 'integration-post'); // unchanged

        // DELETE
        $this->deleteJson("/api/posts/{$postId}")->assertNoContent();

        // READ after delete - 404
        $this->getJson("/api/posts/{$postId}")->assertNotFound();
    }

    // =========================================================================
    // 4. Nested relationship filter
    // =========================================================================

    #[Test]
    public function it_filters_by_nested_relationship_attribute(): void
    {
        $postByJohn = Post::create(['title' => 'Johns Post', 'slug' => 'johns-post']);
        Comment::create(['post_id' => $postByJohn->id, 'author' => 'John', 'body' => 'My comment']);

        $postByJane = Post::create(['title' => 'Janes Post', 'slug' => 'janes-post']);
        Comment::create(['post_id' => $postByJane->id, 'author' => 'Jane', 'body' => 'My comment']);

        $postWithBoth = Post::create(['title' => 'Mixed Post', 'slug' => 'mixed-post']);
        Comment::create(['post_id' => $postWithBoth->id, 'author' => 'John', 'body' => 'Johns comment']);
        Comment::create(['post_id' => $postWithBoth->id, 'author' => 'Jane', 'body' => 'Janes comment']);

        $response = $this->getJson('/api/posts?filter[comments][author]=John');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $returnedTitles = array_map(
            fn ($item) => $item['attributes']['title'],
            $response->json('data'),
        );
        sort($returnedTitles);

        $this->assertSame(['Johns Post', 'Mixed Post'], $returnedTitles);
    }

    // =========================================================================
    // 5. Scope + filter combined
    // =========================================================================

    #[Test]
    public function it_applies_scope_and_filter_together(): void
    {
        Post::create(['title' => 'Public Match', 'slug' => 'public-match', 'published' => true]);
        Post::create(['title' => 'Public Other', 'slug' => 'public-other', 'published' => true]);
        Post::create(['title' => 'Draft Match', 'slug' => 'draft-match', 'published' => false]);
        Post::create(['title' => 'Draft Other', 'slug' => 'draft-other', 'published' => false]);

        // X-Test-Area: public activates TestAreaScope (filters to published=true)
        // filter[title]=Public Match further filters by title
        $response = $this->getJson(
            '/api/scoped-posts?filter[title]=Public Match',
            ['X-Test-Area' => 'public'],
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.title', 'Public Match');
        $response->assertJsonPath('data.0.attributes.published', 1);
    }

    // =========================================================================
    // 6. Relationship endpoint with filter + sort + pagination
    // =========================================================================

    #[Test]
    public function it_combines_filter_sort_and_pagination_on_relationship_endpoint(): void
    {
        $post = Post::create(['title' => 'Parent Post', 'slug' => 'parent-post']);

        for ($i = 1; $i <= 10; $i++) {
            Comment::create([
                'post_id' => $post->id,
                'author' => 'John',
                'body' => "John comment {$i}",
            ]);
        }

        for ($i = 1; $i <= 5; $i++) {
            Comment::create([
                'post_id' => $post->id,
                'author' => 'Jane',
                'body' => "Jane comment {$i}",
            ]);
        }

        // Another post's comments should never appear
        $otherPost = Post::create(['title' => 'Other Post', 'slug' => 'other-post']);
        Comment::create(['post_id' => $otherPost->id, 'author' => 'John', 'body' => 'Other post comment']);

        $response = $this->getJson(
            "/api/posts/{$post->id}/comments?filter[author]=John&sort=-body&page[number]=1&page[size]=5",
        );

        $response->assertOk();
        $response->assertJsonCount(5, 'data');

        // All returned comments belong to John
        foreach ($response->json('data') as $item) {
            $this->assertSame('John', $item['attributes']['author']);
        }

        // Sorted descending by body
        $bodies = array_map(fn ($item) => $item['attributes']['body'], $response->json('data'));
        $sortedBodies = $bodies;
        usort($sortedBodies, fn ($a, $b) => strcmp($b, $a));
        $this->assertSame($sortedBodies, $bodies);

        // Pagination meta shows 10 total (only John's comments on this post)
        $response->assertJsonPath('meta.total', 10);
    }

    // =========================================================================
    // 7. Empty results return correct JSON:API format
    // =========================================================================

    #[Test]
    public function it_returns_correct_json_api_format_for_empty_results(): void
    {
        $response = $this->getJson('/api/posts?filter[slug]=nonexistent');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonPath('data', []);
    }
}

/**
 * Controller using ScopedPostResource for scope + filter integration tests.
 */
class IntegrationScopedPostController extends Controller implements JsonApiController
{
    use HandlesJsonApiIndex;

    public function getResource(): string
    {
        return ScopedPostResource::class;
    }

    public function getModel(): string
    {
        return Post::class;
    }
}
