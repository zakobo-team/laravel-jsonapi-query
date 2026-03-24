<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Tests\Fixtures\Controllers\PostCommentsController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\TestCase;

class RelationshipEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        $router->get('/api/posts/{post}/comments', [PostCommentsController::class, 'index']);
    }

    #[Test]
    public function it_lists_only_comments_belonging_to_the_specific_post(): void
    {
        $post1 = Post::create(['title' => 'Post One', 'slug' => 'post-one']);
        $post2 = Post::create(['title' => 'Post Two', 'slug' => 'post-two']);

        Comment::create(['post_id' => $post1->id, 'author' => 'Alice', 'body' => 'Comment on post 1']);
        Comment::create(['post_id' => $post1->id, 'author' => 'Bob', 'body' => 'Another on post 1']);
        Comment::create(['post_id' => $post2->id, 'author' => 'Charlie', 'body' => 'Comment on post 2']);

        $response = $this->getJson("/api/posts/{$post1->id}/comments");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.type', 'comments');
    }

    #[Test]
    public function it_returns_empty_collection_when_parent_has_no_related_records(): void
    {
        $post = Post::create(['title' => 'Empty Post', 'slug' => 'empty-post']);

        $response = $this->getJson("/api/posts/{$post->id}/comments");

        $response->assertOk();
        $response->assertJsonPath('data', []);
    }

    #[Test]
    public function it_applies_child_resource_filters(): void
    {
        $post = Post::create(['title' => 'Post', 'slug' => 'post']);

        Comment::create(['post_id' => $post->id, 'author' => 'John', 'body' => 'Filtered in']);
        Comment::create(['post_id' => $post->id, 'author' => 'Jane', 'body' => 'Filtered out']);

        $response = $this->getJson("/api/posts/{$post->id}/comments?filter[author]=John");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.author', 'John');
    }

    #[Test]
    public function it_applies_sorting_on_relationship_query(): void
    {
        $post = Post::create(['title' => 'Post', 'slug' => 'post']);

        Comment::create(['post_id' => $post->id, 'author' => 'Zara', 'body' => 'Last alphabetically']);
        Comment::create(['post_id' => $post->id, 'author' => 'Alice', 'body' => 'First alphabetically']);

        $response = $this->getJson("/api/posts/{$post->id}/comments?sort=author");

        $response->assertOk();
        $response->assertJsonPath('data.0.attributes.author', 'Alice');
        $response->assertJsonPath('data.1.attributes.author', 'Zara');
    }

    #[Test]
    public function it_paginates_relationship_query(): void
    {
        $post = Post::create(['title' => 'Post', 'slug' => 'post']);

        for ($i = 1; $i <= 10; $i++) {
            Comment::create(['post_id' => $post->id, 'author' => "Author {$i}", 'body' => "Body {$i}"]);
        }

        $response = $this->getJson("/api/posts/{$post->id}/comments?page[size]=3&page[number]=2");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_returns_404_when_parent_does_not_exist(): void
    {
        $response = $this->getJson('/api/posts/999/comments');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_when_parent_is_soft_deleted(): void
    {
        $post = Post::create(['title' => 'Deleted Post', 'slug' => 'deleted-post']);
        $post->delete();

        $response = $this->getJson("/api/posts/{$post->id}/comments");

        $response->assertNotFound();
    }

    #[Test]
    public function it_resolves_parent_models_from_an_explicit_custom_route_parameter(): void
    {
        $this->app['router']->get('/api/blog-posts/{blog_post}/comments', [CustomRoutePostCommentsController::class, 'index']);

        $post = Post::create(['title' => 'Scoped Post', 'slug' => 'scoped-post']);
        Comment::create(['post_id' => $post->id, 'author' => 'Alice', 'body' => 'Scoped comment']);

        $response = $this->getJson("/api/blog-posts/{$post->id}/comments");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.author', 'Alice');
    }
}

class CustomRoutePostCommentsController extends PostCommentsController
{
    public function getParentRouteParameter(): string
    {
        return 'blog_post';
    }
}
