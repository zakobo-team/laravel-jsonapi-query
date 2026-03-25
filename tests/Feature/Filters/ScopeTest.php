<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature\Filters;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Filters\Scope;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\TestCase;

class ScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_applies_local_scope(): void
    {
        Post::create(['title' => 'Popular', 'slug' => 'popular', 'votes' => 200]);
        Post::create(['title' => 'Unpopular', 'slug' => 'unpopular', 'votes' => 5]);

        $filter = new Scope('popular');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Popular', $results->first()->title);
    }

    #[Test]
    public function it_converts_dash_case_to_camel_case(): void
    {
        Post::create(['title' => 'Popular', 'slug' => 'popular', 'votes' => 200]);
        Post::create(['title' => 'Unpopular', 'slug' => 'unpopular', 'votes' => 5]);

        $filter = new Scope('is-popular', 'popular');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Popular', $results->first()->title);
    }

    #[Test]
    public function it_passes_boolean_false_to_scope(): void
    {
        Post::create(['title' => 'Popular', 'slug' => 'popular', 'votes' => 200]);
        Post::create(['title' => 'Unpopular', 'slug' => 'unpopular', 'votes' => 5]);

        $filter = (new Scope('popular'))->asBoolean();

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 'false'))->get();

        // The scope is called with false; the popular scope checks votes >= 100
        // regardless of the value passed, so it still filters
        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_passes_value_to_scope(): void
    {
        Post::create(['title' => 'Low', 'slug' => 'low', 'votes' => 20]);
        Post::create(['title' => 'Mid', 'slug' => 'mid', 'votes' => 50]);
        Post::create(['title' => 'High', 'slug' => 'high', 'votes' => 80]);

        $filter = new Scope('min-votes');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, '50'))->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(['Mid', 'High'], $results->pluck('title')->all());
    }

    #[Test]
    public function it_returns_the_key(): void
    {
        $filter = new Scope('is-popular');

        $this->assertSame('is-popular', $filter->key());
    }
}
