<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature\Filters;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Filters\FilterDispatcher;
use Zakobo\JsonApiQuery\Filters\WithTrashed;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Country;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\User;
use Zakobo\JsonApiQuery\Tests\TestCase;

class FilterDispatcherTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Attribute filters
    // =========================================================================

    #[Test]
    public function it_dispatches_scalar_attribute_as_where_filter(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first']);
        Post::create(['title' => 'Second', 'slug' => 'second']);

        $request = Request::create('/posts', 'GET', ['filter' => ['slug' => 'first']]);

        $dispatcher = FilterDispatcher::make()->attributes(['slug']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('first', $results->first()->slug);
    }

    #[Test]
    public function it_dispatches_nested_operator_gt_on_attribute(): void
    {
        Post::create(['title' => 'Low', 'slug' => 'low', 'votes' => 10]);
        Post::create(['title' => 'High', 'slug' => 'high', 'votes' => 100]);

        $request = Request::create('/posts', 'GET', ['filter' => ['votes' => ['gt' => 50]]]);

        $dispatcher = FilterDispatcher::make()->attributes(['votes']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('High', $results->first()->title);
    }

    #[Test]
    public function it_dispatches_nested_operator_lte_on_attribute(): void
    {
        Post::create(['title' => 'Low', 'slug' => 'low', 'votes' => 10]);
        Post::create(['title' => 'Mid', 'slug' => 'mid', 'votes' => 50]);
        Post::create(['title' => 'High', 'slug' => 'high', 'votes' => 100]);

        $request = Request::create('/posts', 'GET', ['filter' => ['votes' => ['lte' => 50]]]);

        $dispatcher = FilterDispatcher::make()->attributes(['votes']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(2, $results);
    }

    // =========================================================================
    // Relationship filters
    // =========================================================================

    #[Test]
    public function it_dispatches_has_with_truthy_string(): void
    {
        $postWithComments = Post::create(['title' => 'With', 'slug' => 'with']);
        Comment::create(['post_id' => $postWithComments->id, 'author' => 'John', 'body' => 'A']);

        Post::create(['title' => 'Without', 'slug' => 'without']);

        $request = Request::create('/posts', 'GET', ['filter' => ['comments' => 'true']]);

        $dispatcher = FilterDispatcher::make()->relationships(['comments']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('With', $results->first()->title);
    }

    #[Test]
    public function it_dispatches_has_absence_with_falsy_string(): void
    {
        $postWithComments = Post::create(['title' => 'With', 'slug' => 'with']);
        Comment::create(['post_id' => $postWithComments->id, 'author' => 'John', 'body' => 'A']);

        Post::create(['title' => 'Without', 'slug' => 'without']);

        $request = Request::create('/posts', 'GET', ['filter' => ['comments' => 'false']]);

        $dispatcher = FilterDispatcher::make()->relationships(['comments']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('Without', $results->first()->title);
    }

    #[Test]
    public function it_dispatches_where_has_with_sub_filter_array(): void
    {
        $post1 = Post::create(['title' => 'Post 1', 'slug' => 'post-1']);
        Comment::create(['post_id' => $post1->id, 'author' => 'John', 'body' => 'A']);

        $post2 = Post::create(['title' => 'Post 2', 'slug' => 'post-2']);
        Comment::create(['post_id' => $post2->id, 'author' => 'Jane', 'body' => 'B']);

        $request = Request::create('/posts', 'GET', ['filter' => ['comments' => ['author' => 'John']]]);

        $dispatcher = FilterDispatcher::make()->relationships(['comments']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('Post 1', $results->first()->title);
    }

    #[Test]
    public function it_dispatches_deep_nested_where_has(): void
    {
        $john = User::create(['name' => 'John', 'email' => 'john@test.com']);
        $jane = User::create(['name' => 'Jane', 'email' => 'jane@test.com']);

        $post1 = Post::create(['title' => 'Post 1', 'slug' => 'post-1', 'user_id' => $john->id]);
        $post2 = Post::create(['title' => 'Post 2', 'slug' => 'post-2', 'user_id' => $jane->id]);

        $request = Request::create('/posts', 'GET', ['filter' => ['user' => ['name' => 'John']]]);

        $dispatcher = FilterDispatcher::make()->relationships(['user']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('Post 1', $results->first()->title);
    }

    #[Test]
    public function it_dispatches_truly_nested_relationship_filters_recursively(): void
    {
        $denmark = Country::create(['name' => 'Denmark']);
        $sweden = Country::create(['name' => 'Sweden']);

        $john = User::create(['name' => 'John', 'email' => 'john@test.com', 'country_id' => $denmark->id]);
        $jane = User::create(['name' => 'Jane', 'email' => 'jane@test.com', 'country_id' => $sweden->id]);

        Post::create(['title' => 'Danish Post', 'slug' => 'danish-post', 'user_id' => $john->id]);
        Post::create(['title' => 'Swedish Post', 'slug' => 'swedish-post', 'user_id' => $jane->id]);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['user' => ['country' => ['name' => 'Denmark']]],
        ]);

        $dispatcher = FilterDispatcher::make()->relationships(['user']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('Danish Post', $results->first()->title);
    }

    // =========================================================================
    // Additional filters
    // =========================================================================

    #[Test]
    public function it_delegates_to_registered_additional_filter(): void
    {
        Post::create(['title' => 'Active', 'slug' => 'active']);
        $deleted = Post::create(['title' => 'Deleted', 'slug' => 'deleted']);
        $deleted->delete();

        $request = Request::create('/posts', 'GET', ['filter' => ['with-trashed' => 'true']]);

        $dispatcher = FilterDispatcher::make()->additionalFilters([
            WithTrashed::make(),
        ]);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(2, $results);
    }

    // =========================================================================
    // Security and edge cases
    // =========================================================================

    #[Test]
    public function it_silently_ignores_unknown_filter_keys(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first']);
        Post::create(['title' => 'Second', 'slug' => 'second']);

        $request = Request::create('/posts', 'GET', ['filter' => ['nonexistent' => 'value']]);

        $dispatcher = FilterDispatcher::make()->attributes(['slug']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_handles_non_array_filter_param(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first']);

        $request = Request::create('/posts', 'GET', ['filter' => 'invalid']);

        $dispatcher = FilterDispatcher::make()->attributes(['slug']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_handles_empty_filter_param(): void
    {
        Post::create(['title' => 'First', 'slug' => 'first']);

        $request = Request::create('/posts', 'GET');

        $dispatcher = FilterDispatcher::make()->attributes(['slug']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_applies_empty_string_value_as_filter(): void
    {
        Post::create(['title' => 'With Body', 'slug' => 'with', 'body' => 'content']);
        Post::create(['title' => 'Empty Body', 'slug' => 'empty', 'body' => '']);

        $request = Request::create('/posts', 'GET', ['filter' => ['body' => '']]);

        $dispatcher = FilterDispatcher::make()->attributes(['body']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('Empty Body', $results->first()->title);
    }

    #[Test]
    public function it_treats_has_with_string_one_as_truthy(): void
    {
        $postWithComments = Post::create(['title' => 'With', 'slug' => 'with']);
        Comment::create(['post_id' => $postWithComments->id, 'author' => 'John', 'body' => 'A']);

        Post::create(['title' => 'Without', 'slug' => 'without']);

        $request = Request::create('/posts', 'GET', ['filter' => ['comments' => '1']]);

        $dispatcher = FilterDispatcher::make()->relationships(['comments']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('With', $results->first()->title);
    }

    #[Test]
    public function it_treats_has_with_string_zero_as_falsy(): void
    {
        $postWithComments = Post::create(['title' => 'With', 'slug' => 'with']);
        Comment::create(['post_id' => $postWithComments->id, 'author' => 'John', 'body' => 'A']);

        Post::create(['title' => 'Without', 'slug' => 'without']);

        $request = Request::create('/posts', 'GET', ['filter' => ['comments' => '0']]);

        $dispatcher = FilterDispatcher::make()->relationships(['comments']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('Without', $results->first()->title);
    }

    #[Test]
    public function it_applies_operator_in_relationship_sub_filter(): void
    {
        $post1 = Post::create(['title' => 'Old Post', 'slug' => 'old']);
        Comment::create(['post_id' => $post1->id, 'author' => 'John', 'body' => 'A', 'created_at' => '2023-06-01']);

        $post2 = Post::create(['title' => 'New Post', 'slug' => 'new']);
        Comment::create(['post_id' => $post2->id, 'author' => 'Jane', 'body' => 'B', 'created_at' => '2024-06-01']);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['comments' => ['created_at' => ['gte' => '2024-01-01']]],
        ]);

        $dispatcher = FilterDispatcher::make()->relationships(['comments']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('New Post', $results->first()->title);
    }

    #[Test]
    public function it_applies_range_query_on_attribute(): void
    {
        Post::create(['title' => 'Low', 'slug' => 'low', 'votes' => 5]);
        Post::create(['title' => 'Mid', 'slug' => 'mid', 'votes' => 50]);
        Post::create(['title' => 'High', 'slug' => 'high', 'votes' => 200]);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['votes' => ['gt' => '10', 'lt' => '100']],
        ]);

        $dispatcher = FilterDispatcher::make()->attributes(['votes']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('Mid', $results->first()->title);
    }

    #[Test]
    public function it_dispatches_where_has_with_multiple_sub_filters(): void
    {
        $post1 = Post::create(['title' => 'Post 1', 'slug' => 'post-1']);
        Comment::create(['post_id' => $post1->id, 'author' => 'John', 'body' => 'Great']);

        $post2 = Post::create(['title' => 'Post 2', 'slug' => 'post-2']);
        Comment::create(['post_id' => $post2->id, 'author' => 'John', 'body' => 'Bad']);

        $post3 = Post::create(['title' => 'Post 3', 'slug' => 'post-3']);
        Comment::create(['post_id' => $post3->id, 'author' => 'Jane', 'body' => 'Great']);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['comments' => ['author' => 'John', 'body' => 'Great']],
        ]);

        $dispatcher = FilterDispatcher::make()->relationships(['comments']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('Post 1', $results->first()->title);
    }

    #[Test]
    public function it_combines_attribute_relationship_and_additional_filters(): void
    {
        $post1 = Post::create(['title' => 'Match', 'slug' => 'match', 'votes' => 50]);
        Comment::create(['post_id' => $post1->id, 'author' => 'John', 'body' => 'A']);

        $post2 = Post::create(['title' => 'Wrong Title', 'slug' => 'wrong', 'votes' => 50]);
        Comment::create(['post_id' => $post2->id, 'author' => 'John', 'body' => 'B']);

        $post3 = Post::create(['title' => 'Match', 'slug' => 'no-comments', 'votes' => 50]);

        $deleted = Post::create(['title' => 'Match', 'slug' => 'deleted', 'votes' => 50]);
        Comment::create(['post_id' => $deleted->id, 'author' => 'John', 'body' => 'C']);
        $deleted->delete();

        $request = Request::create('/posts', 'GET', [
            'filter' => [
                'title' => 'Match',
                'comments' => ['author' => 'John'],
                'with-trashed' => 'true',
            ],
        ]);

        $dispatcher = FilterDispatcher::make()
            ->attributes(['title'])
            ->relationships(['comments'])
            ->additionalFilters([WithTrashed::make()]);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(['Match', 'Match'], $results->pluck('title')->all());
    }

    #[Test]
    public function attribute_key_wins_over_relationship_with_same_name(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@test.com']);
        Post::create(['title' => 'Match', 'slug' => 'match', 'user_id' => $user->id]);
        Post::create(['title' => 'No Match', 'slug' => 'no-match', 'user_id' => $user->id]);

        $request = Request::create('/posts', 'GET', ['filter' => ['title' => 'Match']]);

        $dispatcher = FilterDispatcher::make()
            ->attributes(['title'])
            ->relationships(['title']);

        $query = Post::query();
        $dispatcher->apply($query, $request);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('Match', $results->first()->title);
    }
}
