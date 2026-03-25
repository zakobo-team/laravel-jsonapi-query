<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\User;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\CommentResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostConventionalResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostDefaultRelationshipSortResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostDefaultScopeSortResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostResource;
use Zakobo\JsonApiQuery\Tests\TestCase;

class ApplyJsonApiMacroTest extends TestCase
{
    use RefreshDatabase;

    // --- Test 1: Applies filters and sorting, returns Builder ---

    #[Test]
    public function it_applies_filters_and_sorting_and_returns_builder(): void
    {
        Post::create(['title' => 'Alpha', 'slug' => 'alpha', 'votes' => 50]);
        Post::create(['title' => 'Bravo', 'slug' => 'bravo', 'votes' => 200]);
        Post::create(['title' => 'Charlie', 'slug' => 'charlie', 'votes' => 150]);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['votes' => ['gt' => '100']],
            'sort' => '-title',
        ]);

        $builder = Post::query()->applyJsonApi(PostResource::class, $request);

        $this->assertInstanceOf(Builder::class, $builder);

        $results = $builder->get();
        $this->assertCount(2, $results);
        $this->assertSame('Charlie', $results[0]->title);
        $this->assertSame('Bravo', $results[1]->title);
    }

    // --- Test 2: Can chain ->get() after ---

    #[Test]
    public function it_can_chain_get_after(): void
    {
        Post::create(['title' => 'Only Post', 'slug' => 'only']);

        $request = Request::create('/posts', 'GET', ['filter' => ['slug' => 'only']]);

        $results = Post::query()
            ->applyJsonApi(PostResource::class, $request)
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('Only Post', $results->first()->title);
    }

    // --- Test 3: Can chain ->paginate() after ---

    #[Test]
    public function it_can_chain_paginate_after(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['sort' => 'title']);

        $paginator = Post::query()
            ->applyJsonApi(PostResource::class, $request)
            ->paginate(5);

        $this->assertCount(5, $paginator->items());
        $this->assertSame(10, $paginator->total());
    }

    // --- Test 4: Does NOT paginate automatically ---

    #[Test]
    public function it_does_not_paginate_automatically(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET');

        $results = Post::query()
            ->applyJsonApi(PostResource::class, $request)
            ->get();

        // PostResource has defaultPageSize=15, but applyJsonApi should NOT paginate
        $this->assertCount(20, $results);
    }

    // --- Test 5: Relationship sort via macro ---

    #[Test]
    public function it_sorts_by_relationship_via_macro(): void
    {
        $bob = User::create(['name' => 'Bob', 'email' => 'bob@test.com']);
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);

        Post::create(['title' => 'Bob Post', 'slug' => 'bob', 'user_id' => $bob->id]);
        Post::create(['title' => 'Alice Post', 'slug' => 'alice', 'user_id' => $alice->id]);

        $request = Request::create('/posts', 'GET', ['sort' => 'user.name']);

        $results = Post::query()
            ->applyJsonApi(PostResource::class, $request)
            ->get();

        $this->assertSame('Alice Post', $results->first()->title);
        $this->assertSame('Bob Post', $results->last()->title);
    }

    // --- Test 6: Additional sort (ScopeSort) via macro ---

    #[Test]
    public function it_sorts_by_additional_sort_via_macro(): void
    {
        $postOld = Post::create(['title' => 'Old', 'slug' => 'old']);
        Comment::create(['post_id' => $postOld->id, 'author' => 'A', 'body' => 'x', 'created_at' => '2024-01-01']);

        $postNew = Post::create(['title' => 'New', 'slug' => 'new']);
        Comment::create(['post_id' => $postNew->id, 'author' => 'B', 'body' => 'y', 'created_at' => '2024-06-01']);

        $request = Request::create('/posts', 'GET', ['sort' => '-latest-comment']);

        $results = Post::query()
            ->applyJsonApi(PostResource::class, $request)
            ->get();

        $this->assertSame('New', $results->first()->title);
        $this->assertSame('Old', $results->last()->title);
    }

    // --- Test 7: Default sort with relationship sort ---

    #[Test]
    public function it_applies_default_relationship_sort_when_no_sort_param(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $bob = User::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        Post::create(['title' => 'Alice Post', 'slug' => 'alice', 'user_id' => $alice->id]);
        Post::create(['title' => 'Bob Post', 'slug' => 'bob', 'user_id' => $bob->id]);

        $request = Request::create('/posts', 'GET');

        $results = Post::query()
            ->applyJsonApi(PostDefaultRelationshipSortResource::class, $request)
            ->get();

        // defaultSort = '-user.name' → desc → Bob first
        $this->assertSame('Bob Post', $results->first()->title);
        $this->assertSame('Alice Post', $results->last()->title);
    }

    // --- Test 8: Default sort with additional sort (ScopeSort) ---

    #[Test]
    public function it_applies_default_scope_sort_when_no_sort_param(): void
    {
        $postOld = Post::create(['title' => 'Old', 'slug' => 'old']);
        Comment::create(['post_id' => $postOld->id, 'author' => 'A', 'body' => 'x', 'created_at' => '2024-01-01']);

        $postNew = Post::create(['title' => 'New', 'slug' => 'new']);
        Comment::create(['post_id' => $postNew->id, 'author' => 'B', 'body' => 'y', 'created_at' => '2024-06-01']);

        $request = Request::create('/posts', 'GET');

        $results = Post::query()
            ->applyJsonApi(PostDefaultScopeSortResource::class, $request)
            ->get();

        // defaultSort = '-latest-comment' → desc → New first
        $this->assertSame('New', $results->first()->title);
        $this->assertSame('Old', $results->last()->title);
    }

    #[Test]
    public function it_can_infer_the_resource_class(): void
    {
        Post::create(['title' => 'Only Post', 'slug' => 'only']);

        $request = Request::create('/posts', 'GET', ['filter' => ['slug' => 'only']]);

        $results = Post::query()
            ->applyJsonApi($request)
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('Only Post', $results->first()->title);
    }

    #[Test]
    public function it_supports_with_trashed_by_convention_without_resource_configuration(): void
    {
        Post::create(['title' => 'Active', 'slug' => 'active']);
        $deleted = Post::create(['title' => 'Deleted', 'slug' => 'deleted']);
        $deleted->delete();

        $request = Request::create('/posts', 'GET', [
            'filter' => ['with-trashed' => 'true'],
        ]);

        $results = Post::query()
            ->applyJsonApi(PostConventionalResource::class, $request)
            ->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_supports_only_trashed_by_convention_without_resource_configuration(): void
    {
        Post::create(['title' => 'Active', 'slug' => 'active']);
        $deleted = Post::create(['title' => 'Deleted', 'slug' => 'deleted']);
        $deleted->delete();

        $request = Request::create('/posts', 'GET', [
            'filter' => ['only-trashed' => 'true'],
        ]);

        $results = Post::query()
            ->applyJsonApi(PostConventionalResource::class, $request)
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('Deleted', $results->first()->title);
    }

    #[Test]
    public function it_rejects_soft_delete_filters_for_models_without_soft_deletes(): void
    {
        Post::create(['title' => 'Post', 'slug' => 'post']);
        Comment::create(['post_id' => Post::first()->id, 'author' => 'Jane', 'body' => 'Nice']);

        $request = Request::create('/comments', 'GET', [
            'filter' => ['with-trashed' => 'true'],
        ]);

        $this->expectException(\Zakobo\JsonApiQuery\Exceptions\UnknownFilterFieldException::class);

        Comment::query()->applyJsonApi(CommentResource::class, $request)->get();
    }
}
