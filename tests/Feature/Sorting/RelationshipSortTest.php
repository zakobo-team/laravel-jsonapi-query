<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature\Sorting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Sorting\SortApplier;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\PostMeta;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\User;
use Zakobo\JsonApiQuery\Tests\TestCase;

class RelationshipSortTest extends TestCase
{
    use RefreshDatabase;

    private function sortApplier(): SortApplier
    {
        return app(SortApplier::class);
    }

    #[Test]
    public function it_sorts_by_belongs_to_relationship_ascending(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $bob = User::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        Post::create(['title' => 'Bob Post', 'slug' => 'bob-post', 'user_id' => $bob->id]);
        Post::create(['title' => 'Alice Post', 'slug' => 'alice-post', 'user_id' => $alice->id]);

        $request = Request::create('/posts', 'GET', ['sort' => 'user.name']);
        $query = Post::query();

        $this->sortApplier()->apply($query, [], $request, allowedRelationshipSortFields: ['user.name']);

        $results = $query->get();

        $this->assertSame('Alice Post', $results->first()->title);
        $this->assertSame('Bob Post', $results->last()->title);
    }

    #[Test]
    public function it_sorts_by_belongs_to_relationship_descending(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $bob = User::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        Post::create(['title' => 'Alice Post', 'slug' => 'alice-post', 'user_id' => $alice->id]);
        Post::create(['title' => 'Bob Post', 'slug' => 'bob-post', 'user_id' => $bob->id]);

        $request = Request::create('/posts', 'GET', ['sort' => '-user.name']);
        $query = Post::query();

        $this->sortApplier()->apply($query, [], $request, allowedRelationshipSortFields: ['user.name']);

        $results = $query->get();

        $this->assertSame('Bob Post', $results->first()->title);
        $this->assertSame('Alice Post', $results->last()->title);
    }

    #[Test]
    public function it_sorts_by_has_one_relationship(): void
    {
        $post1 = Post::create(['title' => 'Post Zeta', 'slug' => 'post-zeta']);
        PostMeta::create(['post_id' => $post1->id, 'seo_title' => 'Zeta SEO']);

        $post2 = Post::create(['title' => 'Post Alpha', 'slug' => 'post-alpha']);
        PostMeta::create(['post_id' => $post2->id, 'seo_title' => 'Alpha SEO']);

        $request = Request::create('/posts', 'GET', ['sort' => 'meta.seo_title']);
        $query = Post::query();

        $this->sortApplier()->apply($query, [], $request, allowedRelationshipSortFields: ['meta.seo_title']);

        $results = $query->get();

        $this->assertSame('Post Alpha', $results->first()->title);
        $this->assertSame('Post Zeta', $results->last()->title);
    }

    #[Test]
    public function it_combines_relationship_and_attribute_sort(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $bob = User::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        Post::create(['title' => 'B First', 'slug' => 'b1', 'user_id' => $bob->id]);
        Post::create(['title' => 'A First', 'slug' => 'a1', 'user_id' => $alice->id]);
        Post::create(['title' => 'A Second', 'slug' => 'a2', 'user_id' => $alice->id]);

        $request = Request::create('/posts', 'GET', ['sort' => 'user.name,title']);
        $query = Post::query();

        $this->sortApplier()->apply($query, ['title'], $request, allowedRelationshipSortFields: ['user.name']);

        $results = $query->pluck('title')->all();

        $this->assertSame(['A First', 'A Second', 'B First'], $results);
    }

    #[Test]
    public function it_silently_ignores_field_not_in_sortable_relationships(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        Post::create(['title' => 'Post', 'slug' => 'post', 'user_id' => $alice->id]);

        $request = Request::create('/posts', 'GET', ['sort' => 'user.email']);
        $query = Post::query();

        $this->sortApplier()->apply($query, [], $request, allowedRelationshipSortFields: ['user.name']);

        $results = $query->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_silently_ignores_has_many_relationship_sort(): void
    {
        $post = Post::create(['title' => 'Post', 'slug' => 'post']);
        Comment::create(['post_id' => $post->id, 'author' => 'A', 'body' => 'x']);

        $request = Request::create('/posts', 'GET', ['sort' => 'comments.body']);
        $query = Post::query();

        $this->sortApplier()->apply($query, [], $request, allowedRelationshipSortFields: ['comments.body']);

        $results = $query->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_silently_ignores_nonexistent_relationship(): void
    {
        Post::create(['title' => 'Post', 'slug' => 'post']);

        $request = Request::create('/posts', 'GET', ['sort' => 'nonexistent.name']);
        $query = Post::query();

        $this->sortApplier()->apply($query, [], $request, allowedRelationshipSortFields: ['nonexistent.name']);

        $results = $query->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_silently_ignores_multi_hop_relationship_sort(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@test.com']);
        Post::create(['title' => 'Post', 'slug' => 'post', 'user_id' => $user->id]);

        $request = Request::create('/posts', 'GET', ['sort' => 'user.country.name']);
        $query = Post::query();

        $this->sortApplier()->apply($query, [], $request, allowedRelationshipSortFields: ['user.country.name']);

        $results = $query->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_preserves_relationship_constraints_when_sorting(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $bob = User::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        Post::create(['title' => 'Bob Post', 'slug' => 'bob-post', 'user_id' => $bob->id]);
        Post::create(['title' => 'Alice Post', 'slug' => 'alice-post', 'user_id' => $alice->id]);

        $request = Request::create('/posts', 'GET', ['sort' => '-activeUser.name']);
        $query = Post::query();

        $this->sortApplier()->apply($query, [], $request, allowedRelationshipSortFields: ['activeUser.name']);

        $this->assertStringContainsString('"name" = ?', $query->toSql());
        $this->assertContains('Alice', $query->getBindings());
    }
}
