<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature\Scopes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Controller;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Http\Concerns\HandlesJsonApiIndex;
use Zakobo\JsonApiQuery\Http\Contracts\JsonApiController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\ScopedPostResource;
use Zakobo\JsonApiQuery\Tests\TestCase;

class ScopeSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        $router->get('/api/scoped-posts', [ScopedPostController::class, 'index']);
        $router->get('/api/unscoped-posts', [UnscopedPostController::class, 'index']);
    }

    // =========================================================================
    // Scope application
    // =========================================================================

    #[Test]
    public function scope_applies_when_should_apply_returns_true(): void
    {
        Post::create(['title' => 'Published', 'slug' => 'published', 'published' => true]);
        Post::create(['title' => 'Draft', 'slug' => 'draft', 'published' => false]);

        $response = $this->getJson('/api/scoped-posts', ['X-Test-Area' => 'public']);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.title', 'Published');
    }

    #[Test]
    public function scope_does_not_apply_when_should_apply_returns_false(): void
    {
        Post::create(['title' => 'Published', 'slug' => 'published', 'published' => true]);
        Post::create(['title' => 'Draft', 'slug' => 'draft', 'published' => false]);

        // Without the X-Test-Area header, TestAreaScope should not apply
        $response = $this->getJson('/api/scoped-posts');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function multiple_scopes_only_matching_ones_apply(): void
    {
        Post::create(['title' => 'Published', 'slug' => 'published', 'published' => true]);
        Post::create(['title' => 'Draft', 'slug' => 'draft', 'published' => false]);

        // TestAreaScope applies (filters to published), NeverAppliesScope does not apply
        $response = $this->getJson('/api/scoped-posts', ['X-Test-Area' => 'public']);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.title', 'Published');
    }

    #[Test]
    public function resource_with_empty_scoped_by_applies_no_scoping(): void
    {
        Post::create(['title' => 'Published', 'slug' => 'published', 'published' => true]);
        Post::create(['title' => 'Draft', 'slug' => 'draft', 'published' => false]);

        // UnscopedPostController uses PostResource which has empty $scopedBy
        $response = $this->getJson('/api/unscoped-posts', ['X-Test-Area' => 'public']);

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function scope_receives_the_actual_request_object(): void
    {
        Post::create(['title' => 'Published', 'slug' => 'published', 'published' => true]);
        Post::create(['title' => 'Draft', 'slug' => 'draft', 'published' => false]);

        // With X-Test-Area: public header → TestAreaScope reads it and applies
        $response = $this->getJson('/api/scoped-posts', ['X-Test-Area' => 'public']);
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        // With X-Test-Area: admin header → TestAreaScope reads it and does NOT apply
        $response = $this->getJson('/api/scoped-posts', ['X-Test-Area' => 'admin']);
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }
}

/**
 * Controller using ScopedPostResource with scopes configured.
 */
class ScopedPostController extends Controller implements JsonApiController
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

/**
 * Controller using PostResource with empty $scopedBy.
 */
class UnscopedPostController extends Controller implements JsonApiController
{
    use HandlesJsonApiIndex;

    public function getResource(): string
    {
        return PostResource::class;
    }

    public function getModel(): string
    {
        return Post::class;
    }
}
