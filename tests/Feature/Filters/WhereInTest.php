<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature\Filters;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Filters\WhereIdIn;
use Zakobo\JsonApiQuery\Filters\WhereIdNotIn;
use Zakobo\JsonApiQuery\Filters\WhereIn;
use Zakobo\JsonApiQuery\Filters\WhereNotIn;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\TestCase;

class WhereInTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_filters_by_array_of_values(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first']);
        Post::create(['title' => 'Second', 'slug' => 'second']);
        Post::create(['title' => 'Third', 'slug' => 'third']);

        $filter = WhereIn::make('slug');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, ['first', 'third']))->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(['first', 'third'], $results->pluck('slug')->all());
    }

    #[Test]
    public function it_filters_by_comma_delimited_string(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first']);
        Post::create(['title' => 'Second', 'slug' => 'second']);
        Post::create(['title' => 'Third', 'slug' => 'third']);

        $filter = WhereIn::make('slug')->delimiter(',');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 'first,third'))->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(['first', 'third'], $results->pluck('slug')->all());
    }

    #[Test]
    public function it_excludes_values_with_where_not_in(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first']);
        Post::create(['title' => 'Second', 'slug' => 'second']);
        Post::create(['title' => 'Third', 'slug' => 'third']);

        $filter = WhereNotIn::make('slug');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, ['first', 'third']))->get();

        $this->assertCount(1, $results);
        $this->assertSame('second', $results->first()->slug);
    }

    #[Test]
    public function it_filters_by_primary_key_with_where_id_in(): void
    {
        $post1 = Post::create(['title' => 'First', 'slug' => 'first']);
        Post::create(['title' => 'Second', 'slug' => 'second']);
        $post3 = Post::create(['title' => 'Third', 'slug' => 'third']);

        $filter = WhereIdIn::make('id');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, [$post1->id, $post3->id]))->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing([$post1->id, $post3->id], $results->pluck('id')->all());
    }

    #[Test]
    public function it_filters_by_primary_key_with_delimiter(): void
    {
        $post1 = Post::create(['title' => 'First', 'slug' => 'first']);
        Post::create(['title' => 'Second', 'slug' => 'second']);
        $post3 = Post::create(['title' => 'Third', 'slug' => 'third']);

        $filter = WhereIdIn::make('id')->delimiter(',');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, "{$post1->id},{$post3->id}"))->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing([$post1->id, $post3->id], $results->pluck('id')->all());
    }

    #[Test]
    public function it_excludes_by_primary_key_with_where_id_not_in(): void
    {
        $post1 = Post::create(['title' => 'First', 'slug' => 'first']);
        Post::create(['title' => 'Second', 'slug' => 'second']);
        $post3 = Post::create(['title' => 'Third', 'slug' => 'third']);

        $filter = WhereIdNotIn::make('id');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, [$post1->id, $post3->id]))->get();

        $this->assertCount(1, $results);
        $this->assertSame('second', $results->first()->slug);
    }

    #[Test]
    public function it_handles_single_element_array(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first']);
        Post::create(['title' => 'Second', 'slug' => 'second']);

        $filter = WhereIn::make('slug');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, ['first']))->get();

        $this->assertCount(1, $results);
        $this->assertSame('first', $results->first()->slug);
    }

    #[Test]
    public function it_returns_empty_for_empty_array(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first']);

        $filter = WhereIn::make('slug');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, []))->get();

        $this->assertCount(0, $results);
    }

    #[Test]
    public function it_returns_empty_for_nonexistent_ids(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first']);

        $filter = WhereIdIn::make('id');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, [999, 888]))->get();

        $this->assertCount(0, $results);
    }

    #[Test]
    public function it_excludes_values_with_where_not_in_and_delimiter(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first']);
        Post::create(['title' => 'Second', 'slug' => 'second']);
        Post::create(['title' => 'Third', 'slug' => 'third']);

        $filter = WhereNotIn::make('slug')->delimiter(',');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 'first,third'))->get();

        $this->assertCount(1, $results);
        $this->assertSame('second', $results->first()->slug);
    }

    #[Test]
    public function it_treats_non_delimited_string_as_single_value_when_delimiter_set(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first']);
        Post::create(['title' => 'Second', 'slug' => 'second']);

        $filter = WhereIn::make('slug')->delimiter(',');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 'first'))->get();

        $this->assertCount(1, $results);
        $this->assertSame('first', $results->first()->slug);
    }
}
