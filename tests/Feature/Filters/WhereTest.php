<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature\Filters;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Filters\Where;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\TestCase;

class WhereTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_filters_by_exact_value(): void
    {
        Post::create(['title' => 'First Post', 'slug' => 'first-post']);
        Post::create(['title' => 'Second Post', 'slug' => 'second-post']);

        $filter = Where::make('slug');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 'first-post'))->get();

        $this->assertCount(1, $results);
        $this->assertSame('first-post', $results->first()->slug);
    }

    #[Test]
    public function it_filters_using_custom_column(): void
    {
        Post::create(['title' => 'Laravel Tips', 'slug' => 'laravel-tips']);
        Post::create(['title' => 'Vue Guide', 'slug' => 'vue-guide']);

        $filter = Where::make('name', 'title');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 'Laravel Tips'))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Laravel Tips', $results->first()->title);
    }

    #[Test]
    public function it_filters_with_greater_than_operator(): void
    {
        Post::create(['title' => 'Low', 'slug' => 'low', 'votes' => 10]);
        Post::create(['title' => 'High', 'slug' => 'high', 'votes' => 100]);

        $filter = Where::make('votes')->gt();

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 50))->get();

        $this->assertCount(1, $results);
        $this->assertSame('High', $results->first()->title);
    }

    #[Test]
    public function it_filters_with_greater_than_or_equal_operator(): void
    {
        Post::create(['title' => 'Low', 'slug' => 'low', 'votes' => 10]);
        Post::create(['title' => 'Mid', 'slug' => 'mid', 'votes' => 50]);
        Post::create(['title' => 'High', 'slug' => 'high', 'votes' => 100]);

        $filter = Where::make('votes')->gte();

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 50))->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_filters_with_less_than_operator(): void
    {
        Post::create(['title' => 'Low', 'slug' => 'low', 'votes' => 10]);
        Post::create(['title' => 'High', 'slug' => 'high', 'votes' => 100]);

        $filter = Where::make('votes')->lt();

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 50))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Low', $results->first()->title);
    }

    #[Test]
    public function it_filters_with_less_than_or_equal_operator(): void
    {
        Post::create(['title' => 'Low', 'slug' => 'low', 'votes' => 10]);
        Post::create(['title' => 'Mid', 'slug' => 'mid', 'votes' => 50]);
        Post::create(['title' => 'High', 'slug' => 'high', 'votes' => 100]);

        $filter = Where::make('votes')->lte();

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 50))->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_deserializes_boolean_true_values(): void
    {
        Post::create(['title' => 'Published', 'slug' => 'published', 'published' => true]);
        Post::create(['title' => 'Draft', 'slug' => 'draft', 'published' => false]);

        $filter = Where::make('published')->asBoolean();

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 'true'))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Published', $results->first()->title);
    }

    #[Test]
    public function it_deserializes_boolean_false_values(): void
    {
        Post::create(['title' => 'Published', 'slug' => 'published', 'published' => true]);
        Post::create(['title' => 'Draft', 'slug' => 'draft', 'published' => false]);

        $filter = Where::make('published')->asBoolean();

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 'false'))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Draft', $results->first()->title);
    }

    #[Test]
    public function it_deserializes_boolean_string_zero(): void
    {
        Post::create(['title' => 'Published', 'slug' => 'published', 'published' => true]);
        Post::create(['title' => 'Draft', 'slug' => 'draft', 'published' => false]);

        $filter = Where::make('published')->asBoolean();

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, '0'))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Draft', $results->first()->title);
    }

    #[Test]
    public function it_deserializes_boolean_string_one(): void
    {
        Post::create(['title' => 'Published', 'slug' => 'published', 'published' => true]);
        Post::create(['title' => 'Draft', 'slug' => 'draft', 'published' => false]);

        $filter = Where::make('published')->asBoolean();

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, '1'))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Published', $results->first()->title);
    }

    #[Test]
    public function it_applies_custom_deserializer(): void
    {
        Post::create(['title' => 'hello-world', 'slug' => 'hello-world']);
        Post::create(['title' => 'goodbye', 'slug' => 'goodbye']);

        $filter = Where::make('slug')->deserializeUsing(fn ($v) => str_replace(' ', '-', $v));

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 'hello world'))->get();

        $this->assertCount(1, $results);
        $this->assertSame('hello-world', $results->first()->slug);
    }

    #[Test]
    public function it_returns_the_key(): void
    {
        $filter = Where::make('slug');

        $this->assertSame('slug', $filter->key());
    }

    #[Test]
    public function it_returns_empty_when_no_match(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first']);
        Post::create(['title' => 'Second', 'slug' => 'second']);

        $filter = Where::make('slug');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 'nonexistent'))->get();

        $this->assertCount(0, $results);
    }

    #[Test]
    public function it_returns_all_when_all_match(): void
    {
        Post::create(['title' => 'First', 'slug' => 'a', 'votes' => 200]);
        Post::create(['title' => 'Second', 'slug' => 'b', 'votes' => 300]);

        $filter = Where::make('votes')->gt();

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 100))->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_handles_empty_string_value(): void
    {
        Post::create(['title' => 'With Body', 'slug' => 'with-body', 'body' => 'content']);
        Post::create(['title' => 'Empty Body', 'slug' => 'empty-body', 'body' => '']);

        $filter = Where::make('body');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, ''))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Empty Body', $results->first()->title);
    }

    #[Test]
    public function it_handles_zero_integer_value(): void
    {
        Post::create(['title' => 'No Votes', 'slug' => 'no-votes', 'votes' => 0]);
        Post::create(['title' => 'Has Votes', 'slug' => 'has-votes', 'votes' => 5]);

        $filter = Where::make('votes');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 0))->get();

        $this->assertCount(1, $results);
        $this->assertSame('No Votes', $results->first()->title);
    }

    #[Test]
    public function it_handles_string_zero_value(): void
    {
        Post::create(['title' => 'No Votes', 'slug' => 'no-votes', 'votes' => 0]);
        Post::create(['title' => 'Has Votes', 'slug' => 'has-votes', 'votes' => 5]);

        $filter = Where::make('votes');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, '0'))->get();

        $this->assertCount(1, $results);
        $this->assertSame('No Votes', $results->first()->title);
    }

    #[Test]
    public function it_qualifies_column_with_table_name(): void
    {
        Post::create(['title' => 'Test', 'slug' => 'test']);

        $filter = Where::make('slug');
        $query = Post::query();
        $filter->apply($query, 'test');

        $this->assertStringContainsString('posts', $query->toRawSql());
    }
}
