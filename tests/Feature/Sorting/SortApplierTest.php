<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature\Sorting;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Sorting\SortApplier;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\TestCase;

class SortApplierTest extends TestCase
{
    #[Test]
    public function it_sorts_ascending_by_default(): void
    {
        Post::create(['title' => 'Banana', 'slug' => 'banana', 'votes' => 5]);
        Post::create(['title' => 'Apple', 'slug' => 'apple', 'votes' => 10]);
        Post::create(['title' => 'Cherry', 'slug' => 'cherry', 'votes' => 3]);

        $query = Post::query();
        $request = Request::create('/posts', 'GET', ['sort' => 'title']);

        SortApplier::apply($query, ['title', 'slug', 'votes', 'created_at'], $request);

        $titles = $query->pluck('title')->all();

        $this->assertSame(['Apple', 'Banana', 'Cherry'], $titles);
    }

    #[Test]
    public function it_sorts_descending_with_hyphen_prefix(): void
    {
        Post::create(['title' => 'Banana', 'slug' => 'banana', 'votes' => 5]);
        Post::create(['title' => 'Apple', 'slug' => 'apple', 'votes' => 10]);
        Post::create(['title' => 'Cherry', 'slug' => 'cherry', 'votes' => 3]);

        $query = Post::query();
        $request = Request::create('/posts', 'GET', ['sort' => '-title']);

        SortApplier::apply($query, ['title', 'slug', 'votes', 'created_at'], $request);

        $titles = $query->pluck('title')->all();

        $this->assertSame(['Cherry', 'Banana', 'Apple'], $titles);
    }

    #[Test]
    public function it_sorts_by_multiple_fields(): void
    {
        Post::create(['title' => 'Same', 'slug' => 'same-a', 'votes' => 10]);
        Post::create(['title' => 'Same', 'slug' => 'same-b', 'votes' => 5]);
        Post::create(['title' => 'Different', 'slug' => 'different', 'votes' => 8]);

        $query = Post::query();
        $request = Request::create('/posts', 'GET', ['sort' => 'title,-votes']);

        SortApplier::apply($query, ['title', 'slug', 'votes', 'created_at'], $request);

        $results = $query->get();

        $this->assertSame('Different', $results[0]->title);
        $this->assertSame('Same', $results[1]->title);
        $this->assertSame(10, $results[1]->votes);
        $this->assertSame('Same', $results[2]->title);
        $this->assertSame(5, $results[2]->votes);
    }

    #[Test]
    public function it_applies_default_sort_when_no_sort_param_provided(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first', 'votes' => 1]);
        Post::create(['title' => 'Second', 'slug' => 'second', 'votes' => 2]);
        Post::create(['title' => 'Third', 'slug' => 'third', 'votes' => 3]);

        $query = Post::query();
        $request = Request::create('/posts', 'GET');

        SortApplier::apply($query, ['title', 'slug', 'votes', 'created_at'], $request, '-votes');

        $votes = $query->pluck('votes')->all();

        $this->assertSame([3, 2, 1], $votes);
    }

    #[Test]
    public function it_applies_default_sort_even_when_the_default_field_is_not_client_sortable(): void
    {
        Post::create([
            'title' => 'Older',
            'slug' => 'older',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);
        Post::create([
            'title' => 'Newer',
            'slug' => 'newer',
            'created_at' => '2024-02-01 00:00:00',
            'updated_at' => '2024-02-01 00:00:00',
        ]);

        $query = Post::query();
        $request = Request::create('/posts', 'GET');

        SortApplier::apply($query, ['title', 'slug', 'votes'], $request, '-created_at');

        $titles = $query->pluck('title')->all();

        $this->assertSame(['Newer', 'Older'], $titles);
    }

    #[Test]
    public function it_ignores_disallowed_sort_fields(): void
    {
        Post::create(['title' => 'Banana', 'slug' => 'banana', 'votes' => 5]);
        Post::create(['title' => 'Apple', 'slug' => 'apple', 'votes' => 10]);

        $query = Post::query();
        $request = Request::create('/posts', 'GET', ['sort' => 'body']);

        SortApplier::apply($query, ['title', 'slug', 'votes'], $request);

        $this->assertEmpty($query->getQuery()->orders);
    }

    #[Test]
    public function it_applies_no_ordering_when_no_sort_param_and_no_default(): void
    {
        Post::create(['title' => 'A', 'slug' => 'a']);

        $query = Post::query();
        $request = Request::create('/posts', 'GET');

        SortApplier::apply($query, ['title', 'slug', 'votes'], $request);

        $this->assertEmpty($query->getQuery()->orders);
    }

    #[Test]
    public function it_applies_no_ordering_for_empty_sort_parameter(): void
    {
        $query = Post::query();
        $request = Request::create('/posts', 'GET', ['sort' => '']);

        SortApplier::apply($query, ['title', 'slug', 'votes'], $request);

        $this->assertEmpty($query->getQuery()->orders);
    }

    #[Test]
    public function it_ignores_a_single_hyphen_only(): void
    {
        $query = Post::query();
        $request = Request::create('/posts', 'GET', ['sort' => '-']);

        SortApplier::apply($query, ['title', 'slug', 'votes'], $request);

        $this->assertEmpty($query->getQuery()->orders);
    }

    #[Test]
    public function it_sorts_ascending_by_a_field_containing_a_hyphen(): void
    {
        Post::create(['title' => 'B', 'slug' => 'b']);
        Post::create(['title' => 'A', 'slug' => 'a']);

        $query = Post::query();
        $request = Request::create('/posts', 'GET', ['sort' => 'created_at']);

        SortApplier::apply($query, ['title', 'created_at'], $request);

        $orders = $query->getQuery()->orders;

        $this->assertCount(1, $orders);
        $this->assertStringContainsString('created_at', $orders[0]['column']);
        $this->assertSame('asc', $orders[0]['direction']);
    }

    #[Test]
    public function it_applies_only_allowed_fields_from_a_mixed_list(): void
    {
        Post::create(['title' => 'B', 'slug' => 'b', 'votes' => 1]);
        Post::create(['title' => 'A', 'slug' => 'a', 'votes' => 2]);

        $query = Post::query();
        $request = Request::create('/posts', 'GET', ['sort' => 'title,-body,votes']);

        SortApplier::apply($query, ['title', 'votes'], $request);

        $orders = $query->getQuery()->orders;

        $this->assertCount(2, $orders);
        $this->assertStringContainsString('title', $orders[0]['column']);
        $this->assertSame('asc', $orders[0]['direction']);
        $this->assertStringContainsString('votes', $orders[1]['column']);
        $this->assertSame('asc', $orders[1]['direction']);
    }
}
