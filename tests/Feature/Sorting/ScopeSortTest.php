<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature\Sorting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Sorting\ScopeSort;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\TestCase;

class ScopeSortTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sorts_descending_via_model_scope(): void
    {
        $postOld = Post::create(['title' => 'Old', 'slug' => 'old']);
        Comment::create(['post_id' => $postOld->id, 'author' => 'A', 'body' => 'x', 'created_at' => '2024-01-01']);

        $postNew = Post::create(['title' => 'New', 'slug' => 'new']);
        Comment::create(['post_id' => $postNew->id, 'author' => 'B', 'body' => 'y', 'created_at' => '2024-06-01']);

        $query = Post::query();
        $sort = new ScopeSort('latest-comment');
        $sort->apply($query, 'desc');

        $results = $query->get();

        $this->assertSame('New', $results->first()->title);
        $this->assertSame('Old', $results->last()->title);
    }

    #[Test]
    public function it_sorts_ascending_via_model_scope(): void
    {
        $postOld = Post::create(['title' => 'Old', 'slug' => 'old']);
        Comment::create(['post_id' => $postOld->id, 'author' => 'A', 'body' => 'x', 'created_at' => '2024-01-01']);

        $postNew = Post::create(['title' => 'New', 'slug' => 'new']);
        Comment::create(['post_id' => $postNew->id, 'author' => 'B', 'body' => 'y', 'created_at' => '2024-06-01']);

        $query = Post::query();
        $sort = new ScopeSort('latest-comment');
        $sort->apply($query, 'asc');

        $results = $query->get();

        $this->assertSame('Old', $results->first()->title);
        $this->assertSame('New', $results->last()->title);
    }

    #[Test]
    public function it_returns_correct_key(): void
    {
        $sort = new ScopeSort('latest-comment');

        $this->assertSame('latest-comment', $sort->key());
    }
}
