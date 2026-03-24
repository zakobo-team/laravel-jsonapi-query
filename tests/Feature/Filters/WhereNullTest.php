<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature\Filters;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Filters\WhereNotNull;
use Zakobo\JsonApiQuery\Filters\WhereNull;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\TestCase;

class WhereNullTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_null_records_when_value_is_truthy(): void
    {
        Post::create(['title' => 'With Body', 'slug' => 'with-body', 'body' => 'content']);
        Post::create(['title' => 'No Body', 'slug' => 'no-body', 'body' => null]);

        $filter = WhereNull::make('body');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
        $this->assertSame('No Body', $results->first()->title);
    }

    #[Test]
    public function it_returns_not_null_records_when_value_is_falsy(): void
    {
        Post::create(['title' => 'With Body', 'slug' => 'with-body', 'body' => 'content']);
        Post::create(['title' => 'No Body', 'slug' => 'no-body', 'body' => null]);

        $filter = WhereNull::make('body');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, false))->get();

        $this->assertCount(1, $results);
        $this->assertSame('With Body', $results->first()->title);
    }

    #[Test]
    public function it_returns_not_null_records_when_where_not_null_with_truthy_value(): void
    {
        Post::create(['title' => 'With Body', 'slug' => 'with-body', 'body' => 'content']);
        Post::create(['title' => 'No Body', 'slug' => 'no-body', 'body' => null]);

        $filter = WhereNotNull::make('body');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
        $this->assertSame('With Body', $results->first()->title);
    }

    #[Test]
    public function it_handles_all_null_values(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first', 'body' => null]);
        Post::create(['title' => 'Second', 'slug' => 'second', 'body' => null]);

        $filter = WhereNull::make('body');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_handles_none_null_values(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first', 'body' => 'a']);
        Post::create(['title' => 'Second', 'slug' => 'second', 'body' => 'b']);

        $filter = WhereNull::make('body');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(0, $results);
    }

    #[Test]
    public function it_treats_string_one_as_boolean_true(): void
    {
        Post::create(['title' => 'With Body', 'slug' => 'with-body', 'body' => 'content']);
        Post::create(['title' => 'No Body', 'slug' => 'no-body', 'body' => null]);

        $filter = WhereNull::make('body');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, '1'))->get();

        $this->assertCount(1, $results);
        $this->assertSame('No Body', $results->first()->title);
    }

    #[Test]
    public function it_treats_string_zero_as_boolean_false(): void
    {
        Post::create(['title' => 'With Body', 'slug' => 'with-body', 'body' => 'content']);
        Post::create(['title' => 'No Body', 'slug' => 'no-body', 'body' => null]);

        $filter = WhereNull::make('body');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, '0'))->get();

        $this->assertCount(1, $results);
        $this->assertSame('With Body', $results->first()->title);
    }
}
