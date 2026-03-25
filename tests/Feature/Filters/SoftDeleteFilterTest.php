<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature\Filters;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Filters\OnlyTrashed;
use Zakobo\JsonApiQuery\Filters\WithTrashed;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\TestCase;

class SoftDeleteFilterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function with_trashed_includes_soft_deleted_records(): void
    {
        Post::create(['title' => 'Active', 'slug' => 'active']);
        $deleted = Post::create(['title' => 'Deleted', 'slug' => 'deleted']);
        $deleted->delete();

        $filter = new WithTrashed;

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function with_trashed_excludes_soft_deleted_when_falsy(): void
    {
        Post::create(['title' => 'Active', 'slug' => 'active']);
        $deleted = Post::create(['title' => 'Deleted', 'slug' => 'deleted']);
        $deleted->delete();

        $filter = new WithTrashed;

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, false))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Active', $results->first()->title);
    }

    #[Test]
    public function only_trashed_returns_only_soft_deleted_records(): void
    {
        Post::create(['title' => 'Active', 'slug' => 'active']);
        $deleted = Post::create(['title' => 'Deleted', 'slug' => 'deleted']);
        $deleted->delete();

        $filter = new OnlyTrashed;

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Deleted', $results->first()->title);
    }

    #[Test]
    public function with_trashed_when_none_are_trashed(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first']);
        Post::create(['title' => 'Second', 'slug' => 'second']);

        $filter = new WithTrashed;

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function with_trashed_treats_string_one_as_truthy(): void
    {
        Post::create(['title' => 'Active', 'slug' => 'active']);
        $deleted = Post::create(['title' => 'Deleted', 'slug' => 'deleted']);
        $deleted->delete();

        $filter = new WithTrashed;

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, '1'))->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function with_trashed_gracefully_handles_model_without_soft_deletes(): void
    {
        Post::create(['title' => 'Post', 'slug' => 'post']);
        $comment = Comment::create(['post_id' => Post::first()->id, 'author' => 'John', 'body' => 'Nice']);

        $filter = new WithTrashed;

        $results = Comment::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function only_trashed_gracefully_handles_model_without_soft_deletes(): void
    {
        Post::create(['title' => 'Post', 'slug' => 'post']);
        Comment::create(['post_id' => Post::first()->id, 'author' => 'John', 'body' => 'Nice']);

        $filter = new OnlyTrashed;

        $results = Comment::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
    }
}
