<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Tests\Fixtures\Controllers\Invokable\DestroyPostController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Controllers\Invokable\IndexPostController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Controllers\Invokable\ShowPostController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Controllers\Invokable\StorePostController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Controllers\Invokable\UpdatePostController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\TestCase;

class InvokableCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        $router->get('/api/posts', IndexPostController::class);
        $router->get('/api/posts/{post}', ShowPostController::class);
        $router->post('/api/posts', StorePostController::class);
        $router->patch('/api/posts/{post}', UpdatePostController::class);
        $router->delete('/api/posts/{post}', DestroyPostController::class);
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
    public function index_applies_filters(): void
    {
        Post::create(['title' => 'Target', 'slug' => 'target']);
        Post::create(['title' => 'Other', 'slug' => 'other']);

        $response = $this->getJson('/api/posts?filter[slug]=target');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.slug', 'target');
    }

    #[Test]
    public function index_applies_sorting(): void
    {
        Post::create(['title' => 'Zebra', 'slug' => 'zebra']);
        Post::create(['title' => 'Alpha', 'slug' => 'alpha']);

        $response = $this->getJson('/api/posts?sort=title');

        $response->assertOk();
        $response->assertJsonPath('data.0.attributes.title', 'Alpha');
        $response->assertJsonPath('data.1.attributes.title', 'Zebra');
    }

    // =========================================================================
    // Show
    // =========================================================================

    #[Test]
    public function show_returns_single_resource(): void
    {
        $post = Post::create(['title' => 'Show Me', 'slug' => 'show-me']);

        $response = $this->getJson("/api/posts/{$post->id}");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonPath('data.type', 'posts');
        $response->assertJsonPath('data.id', (string) $post->id);
        $response->assertJsonPath('data.attributes.title', 'Show Me');
    }

    // =========================================================================
    // Store
    // =========================================================================

    #[Test]
    public function store_creates_and_returns_201(): void
    {
        $response = $this->postJson('/api/posts', [
            'data' => [
                'type' => 'posts',
                'attributes' => [
                    'title' => 'Invokable Post',
                    'slug' => 'invokable-post',
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.attributes.title', 'Invokable Post');
        $this->assertDatabaseHas('posts', ['title' => 'Invokable Post']);
    }

    // =========================================================================
    // Update
    // =========================================================================

    #[Test]
    public function update_modifies_resource(): void
    {
        $post = Post::create(['title' => 'Before', 'slug' => 'before']);

        $response = $this->patchJson("/api/posts/{$post->id}", [
            'data' => [
                'type' => 'posts',
                'id' => (string) $post->id,
                'attributes' => [
                    'title' => 'After',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.attributes.title', 'After');
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'After']);
    }

    // =========================================================================
    // Destroy
    // =========================================================================

    #[Test]
    public function destroy_soft_deletes_and_returns_204(): void
    {
        $post = Post::create(['title' => 'Remove Me', 'slug' => 'remove-me']);

        $response = $this->deleteJson("/api/posts/{$post->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }
}
