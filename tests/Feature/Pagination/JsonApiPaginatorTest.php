<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature\Pagination;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Pagination\JsonApiPaginator;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\TestCase;

class JsonApiPaginatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_paginates_with_page_number_and_page_size(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['number' => '2', 'size' => '10']]);

        $result = JsonApiPaginator::apply(Post::query(), $request, defaultPageSize: 30, maxPageSize: 100);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(10, $result->items());
        $this->assertSame(2, $result->currentPage());
        $this->assertSame(25, $result->total());
        $this->assertSame('Post 11', $result->items()[0]->title);
    }

    #[Test]
    public function it_uses_default_page_size_when_not_specified(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['number' => '1']]);

        $result = JsonApiPaginator::apply(Post::query(), $request, defaultPageSize: 15, maxPageSize: 100);

        $this->assertCount(15, $result->items());
        $this->assertSame(1, $result->currentPage());
    }

    #[Test]
    public function it_returns_correct_total_count(): void
    {
        for ($i = 1; $i <= 42; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['number' => '1', 'size' => '10']]);

        $result = JsonApiPaginator::apply(Post::query(), $request, defaultPageSize: 30, maxPageSize: 100);

        $this->assertSame(42, $result->total());
        $this->assertSame(5, $result->lastPage());
    }

    #[Test]
    public function it_returns_correct_current_page_number(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['number' => '3', 'size' => '10']]);

        $result = JsonApiPaginator::apply(Post::query(), $request, defaultPageSize: 30, maxPageSize: 100);

        $this->assertSame(3, $result->currentPage());
        $this->assertCount(10, $result->items());
        $this->assertSame('Post 21', $result->items()[0]->title);
    }

    #[Test]
    public function it_caps_page_size_at_max_when_exceeded(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['number' => '1', 'size' => '9999']]);

        $result = JsonApiPaginator::apply(Post::query(), $request, defaultPageSize: 30, maxPageSize: 25);

        $this->assertCount(25, $result->items());
        $this->assertSame(25, $result->perPage());
    }

    #[Test]
    public function it_treats_page_number_zero_as_page_one(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['number' => '0', 'size' => '5']]);

        $result = JsonApiPaginator::apply(Post::query(), $request, defaultPageSize: 30, maxPageSize: 100);

        $this->assertSame(1, $result->currentPage());
        $this->assertSame('Post 1', $result->items()[0]->title);
    }

    #[Test]
    public function it_uses_default_size_when_page_size_is_zero(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['number' => '1', 'size' => '0']]);

        $result = JsonApiPaginator::apply(Post::query(), $request, defaultPageSize: 20, maxPageSize: 100);

        $this->assertSame(20, $result->perPage());
        $this->assertCount(20, $result->items());
    }

    #[Test]
    public function it_uses_default_size_when_page_size_is_negative(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['number' => '1', 'size' => '-5']]);

        $result = JsonApiPaginator::apply(Post::query(), $request, defaultPageSize: 20, maxPageSize: 100);

        $this->assertSame(20, $result->perPage());
        $this->assertCount(20, $result->items());
    }

    #[Test]
    public function it_treats_negative_page_number_as_page_one(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['number' => '-1', 'size' => '5']]);

        $result = JsonApiPaginator::apply(Post::query(), $request, defaultPageSize: 30, maxPageSize: 100);

        $this->assertSame(1, $result->currentPage());
        $this->assertSame('Post 1', $result->items()[0]->title);
    }

    #[Test]
    public function it_uses_page_one_with_default_size_when_no_page_parameters(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET');

        $result = JsonApiPaginator::apply(Post::query(), $request, defaultPageSize: 30, maxPageSize: 100);

        $this->assertSame(1, $result->currentPage());
        $this->assertSame(30, $result->perPage());
        $this->assertCount(30, $result->items());
    }

    #[Test]
    public function it_accepts_custom_default_and_max_sizes(): void
    {
        for ($i = 1; $i <= 100; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET');

        $result = JsonApiPaginator::apply(Post::query(), $request, defaultPageSize: 7, maxPageSize: 50);

        $this->assertSame(7, $result->perPage());
        $this->assertCount(7, $result->items());
    }

    #[Test]
    public function it_sets_page_name_for_json_api_compatible_links(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['number' => '1', 'size' => '5']]);

        $result = JsonApiPaginator::apply(Post::query(), $request, defaultPageSize: 30, maxPageSize: 100);

        $this->assertSame('page[number]', $result->getPageName());
    }

    #[Test]
    public function it_appends_page_size_to_pagination_links(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['number' => '1', 'size' => '5']]);

        $result = JsonApiPaginator::apply(Post::query(), $request, defaultPageSize: 30, maxPageSize: 100);

        $nextPageUrl = $result->nextPageUrl();
        $this->assertStringContainsString('page%5Bsize%5D=5', $nextPageUrl);
    }
}
