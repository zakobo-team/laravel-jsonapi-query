<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Http\Concerns\HandlesJsonApiStore;
use Zakobo\JsonApiQuery\Http\Contracts\JsonApiController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Controllers\PostController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\User;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostResource;
use Zakobo\JsonApiQuery\Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        $router->get('/api/posts', [PostController::class, 'index']);
        $router->get('/api/posts/{post}', [PostController::class, 'show']);
        $router->post('/api/posts', [PostController::class, 'store']);
        $router->patch('/api/posts/{post}', [PostController::class, 'update']);
        $router->delete('/api/posts/{post}', [PostController::class, 'destroy']);
    }

    // =========================================================================
    // Index
    // =========================================================================

    #[Test]
    public function index_returns_json_api_collection(): void
    {
        Post::create(['title' => 'First Post', 'slug' => 'first-post']);
        Post::create(['title' => 'Second Post', 'slug' => 'second-post']);

        $response = $this->getJson('/api/posts');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['type', 'id', 'attributes'],
            ],
        ]);
    }

    #[Test]
    public function index_applies_filter_from_request(): void
    {
        Post::create(['title' => 'Hello World', 'slug' => 'hello']);
        Post::create(['title' => 'Goodbye World', 'slug' => 'goodbye']);

        $response = $this->getJson('/api/posts?filter[slug]=hello');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.slug', 'hello');
    }

    #[Test]
    public function index_applies_sorting(): void
    {
        Post::create(['title' => 'Banana', 'slug' => 'banana']);
        Post::create(['title' => 'Apple', 'slug' => 'apple']);

        $response = $this->getJson('/api/posts?sort=title');

        $response->assertOk();
        $response->assertJsonPath('data.0.attributes.title', 'Apple');
        $response->assertJsonPath('data.1.attributes.title', 'Banana');
    }

    #[Test]
    public function index_uses_resource_default_sort_when_no_sort_parameter_is_present(): void
    {
        Post::create([
            'title' => 'Older Post',
            'slug' => 'older-post',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        Post::create([
            'title' => 'Newer Post',
            'slug' => 'newer-post',
            'created_at' => '2024-02-01 00:00:00',
            'updated_at' => '2024-02-01 00:00:00',
        ]);

        $response = $this->getJson('/api/posts');

        $response->assertOk();
        $response->assertJsonPath('data.0.attributes.title', 'Newer Post');
        $response->assertJsonPath('data.1.attributes.title', 'Older Post');
    }

    #[Test]
    public function index_paginates_with_page_params(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $response = $this->getJson('/api/posts?page[size]=5&page[number]=2');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    #[Test]
    public function index_with_no_records_returns_empty_data_array(): void
    {
        $response = $this->getJson('/api/posts');

        $response->assertOk();
        $response->assertJsonPath('data', []);
    }

    #[Test]
    public function index_with_valid_include_eager_loads_relationship(): void
    {
        $post = Post::create(['title' => 'Post', 'slug' => 'post']);
        Comment::create(['post_id' => $post->id, 'author' => 'John', 'body' => 'Great']);

        $response = $this->getJson('/api/posts?include=comments');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function index_includes_requested_relationships_in_json_api_response(): void
    {
        $post = Post::create(['title' => 'Post', 'slug' => 'post']);
        Comment::create(['post_id' => $post->id, 'author' => 'John', 'body' => 'Great']);

        $response = $this->getJson('/api/posts?include=comments');

        $response->assertOk();
        $response->assertJsonCount(1, 'included');
        $response->assertJsonPath('included.0.type', 'comments');
        $response->assertJsonPath('included.0.attributes.author', 'John');
    }

    // =========================================================================
    // Show
    // =========================================================================

    #[Test]
    public function show_returns_single_resource(): void
    {
        $post = Post::create(['title' => 'My Post', 'slug' => 'my-post', 'votes' => 42]);

        $response = $this->getJson("/api/posts/{$post->id}");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonPath('data.type', 'posts');
        $response->assertJsonPath('data.id', (string) $post->id);
        $response->assertJsonPath('data.attributes.title', 'My Post');
        $response->assertJsonPath('data.attributes.slug', 'my-post');
    }

    #[Test]
    public function show_returns_404_for_nonexistent_id(): void
    {
        $response = $this->getJson('/api/posts/999');

        $response->assertNotFound();
    }

    #[Test]
    public function show_returns_404_for_soft_deleted_post(): void
    {
        $post = Post::create(['title' => 'Deleted', 'slug' => 'deleted']);
        $post->delete();

        $response = $this->getJson("/api/posts/{$post->id}");

        $response->assertNotFound();
    }

    // =========================================================================
    // Store
    // =========================================================================

    #[Test]
    public function store_creates_resource_and_returns_201(): void
    {
        $response = $this->postJson('/api/posts', [
            'data' => [
                'type' => 'posts',
                'attributes' => [
                    'title' => 'New Post',
                    'slug' => 'new-post',
                    'votes' => 10,
                    'published' => true,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonPath('data.attributes.title', 'New Post');
        $response->assertJsonPath('data.attributes.slug', 'new-post');

        $this->assertDatabaseHas('posts', ['title' => 'New Post', 'slug' => 'new-post']);
    }

    #[Test]
    public function store_fills_belongs_to_relationship_from_request(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@test.com']);

        $response = $this->postJson('/api/posts', [
            'data' => [
                'type' => 'posts',
                'attributes' => [
                    'title' => 'User Post',
                    'slug' => 'user-post',
                ],
                'relationships' => [
                    'user' => [
                        'data' => ['type' => 'users', 'id' => $user->id],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', ['title' => 'User Post', 'user_id' => $user->id]);
    }

    #[Test]
    public function store_returns_created_resource_in_response(): void
    {
        $response = $this->postJson('/api/posts', [
            'data' => [
                'type' => 'posts',
                'attributes' => [
                    'title' => 'Response Post',
                    'slug' => 'response-post',
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.type', 'posts');
        $response->assertJsonPath('data.attributes.title', 'Response Post');
        $this->assertNotNull($response->json('data.id'));
    }

    // =========================================================================
    // Update
    // =========================================================================

    #[Test]
    public function update_modifies_resource_attributes(): void
    {
        $post = Post::create(['title' => 'Original', 'slug' => 'original', 'votes' => 0]);

        $response = $this->patchJson("/api/posts/{$post->id}", [
            'data' => [
                'type' => 'posts',
                'id' => (string) $post->id,
                'attributes' => [
                    'title' => 'Updated',
                    'votes' => 99,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.attributes.title', 'Updated');

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'Updated', 'votes' => 99]);
    }

    #[Test]
    public function update_with_partial_attributes_only_changes_what_is_sent(): void
    {
        $post = Post::create(['title' => 'Original', 'slug' => 'original', 'votes' => 42]);

        $response = $this->patchJson("/api/posts/{$post->id}", [
            'data' => [
                'type' => 'posts',
                'id' => (string) $post->id,
                'attributes' => [
                    'title' => 'Changed Title',
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Changed Title',
            'slug' => 'original',
            'votes' => 42,
        ]);
    }

    // =========================================================================
    // Destroy
    // =========================================================================

    #[Test]
    public function destroy_soft_deletes_and_returns_204(): void
    {
        $post = Post::create(['title' => 'To Delete', 'slug' => 'to-delete']);

        $response = $this->deleteJson("/api/posts/{$post->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    #[Test]
    public function destroy_returns_404_for_nonexistent_post(): void
    {
        $response = $this->deleteJson('/api/posts/999');

        $response->assertNotFound();
    }

    // =========================================================================
    // Lifecycle hooks
    // =========================================================================

    #[Test]
    public function created_lifecycle_hook_fires(): void
    {
        // Use a controller with a created() hook by registering a custom route
        $this->app['router']->post('/api/posts-hooked', [PostControllerWithHook::class, 'store']);

        $response = $this->postJson('/api/posts-hooked', [
            'data' => [
                'type' => 'posts',
                'attributes' => [
                    'title' => 'Hooked Post',
                    'slug' => 'hooked-post',
                    'votes' => 0,
                ],
            ],
        ]);

        $response->assertStatus(201);

        // The hook sets votes to 999 after creation
        $this->assertDatabaseHas('posts', ['title' => 'Hooked Post', 'votes' => 999]);
    }
}

/**
 * Controller with a created() lifecycle hook for testing.
 */
class PostControllerWithHook extends Controller implements JsonApiController
{
    use HandlesJsonApiStore;

    public function getResource(): string
    {
        return PostResource::class;
    }

    public function getModel(): string
    {
        return Post::class;
    }

    protected function created(Post $model, Request $request): void
    {
        $model->votes = 999;
        $model->save();
    }
}
