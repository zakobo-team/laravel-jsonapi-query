<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Exceptions\UnsupportedFilterFieldException;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\PlainResourcePost;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\User;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\ConfigurablePlainPostResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\ConfigurableUserResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PlainPostResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\SnakeCasePostResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\UnpaginatedConfigurablePlainPostResource;
use Zakobo\JsonApiQuery\Tests\TestCase;

class NativeJsonApiResourceSupportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function apply_json_api_works_with_plain_laravel_json_api_resource(): void
    {
        Post::create(['title' => 'Alpha', 'slug' => 'alpha', 'votes' => 50]);
        Post::create(['title' => 'Bravo', 'slug' => 'bravo', 'votes' => 200]);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['votes' => ['gt' => '100']],
        ]);

        $results = Post::query()
            ->applyJsonApi(PlainPostResource::class, $request)
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame(['Bravo'], $results->pluck('title')->all());
    }

    #[Test]
    public function apply_json_api_can_infer_a_native_json_api_resource_from_the_model(): void
    {
        PlainResourcePost::create(['title' => 'Alpha', 'slug' => 'alpha', 'votes' => 50]);
        PlainResourcePost::create(['title' => 'Bravo', 'slug' => 'bravo', 'votes' => 200]);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['votes' => ['gt' => '100']],
        ]);

        $results = PlainResourcePost::query()
            ->applyJsonApi($request)
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame(['Bravo'], $results->pluck('title')->all());
    }

    #[Test]
    public function json_api_collection_can_infer_a_native_json_api_resource_from_the_model(): void
    {
        PlainResourcePost::create(['title' => 'Alpha', 'slug' => 'alpha', 'votes' => 50]);
        PlainResourcePost::create(['title' => 'Bravo', 'slug' => 'bravo', 'votes' => 200]);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['votes' => ['gt' => '100']],
        ]);

        $response = PlainResourcePost::query()->jsonApiCollection($request)->response($request);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']);
        $this->assertSame('Bravo', $data['data'][0]['attributes']['title']);
    }

    #[Test]
    public function configurable_plain_resource_can_define_default_sort_and_additional_sort(): void
    {
        $postOld = Post::create(['title' => 'Old', 'slug' => 'old']);
        Comment::create(['post_id' => $postOld->id, 'author' => 'A', 'body' => 'x', 'created_at' => '2024-01-01']);

        $postNew = Post::create(['title' => 'New', 'slug' => 'new']);
        Comment::create(['post_id' => $postNew->id, 'author' => 'B', 'body' => 'y', 'created_at' => '2024-06-01']);

        $request = Request::create('/posts', 'GET');

        $results = Post::query()
            ->applyJsonApi(ConfigurablePlainPostResource::class, $request)
            ->get();

        $this->assertSame(['New', 'Old'], $results->pluck('title')->all());
    }

    #[Test]
    public function configurable_plain_resource_can_exclude_auto_filterable_fields(): void
    {
        Post::create(['title' => 'Alpha', 'slug' => 'alpha', 'votes' => 50]);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['created_at' => '2024-01-01 00:00:00'],
        ]);

        $this->expectException(UnsupportedFilterFieldException::class);

        Post::query()->applyJsonApi(ConfigurablePlainPostResource::class, $request)->get();
    }

    #[Test]
    public function additional_filter_overrides_auto_generated_attribute_filter_with_same_key(): void
    {
        Post::create(['title' => 'Alpha', 'slug' => 'alpha']);
        Post::create(['title' => 'Alpha Plus', 'slug' => 'alpha-plus']);
        Post::create(['title' => 'Bravo', 'slug' => 'bravo']);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['title' => 'Alpha'],
        ]);

        $results = Post::query()
            ->applyJsonApi(ConfigurablePlainPostResource::class, $request)
            ->get();

        $this->assertCount(2, $results);
        $this->assertSame(['Alpha', 'Alpha Plus'], $results->pluck('title')->sort()->values()->all());
    }

    #[Test]
    public function additional_filter_overrides_relationship_filter_with_same_key(): void
    {
        $userA = User::create(['name' => 'Alice', 'email' => 'alice@example.test']);
        $userB = User::create(['name' => 'Bob', 'email' => 'bob@example.test']);

        $postOne = Post::create(['title' => 'Alpha', 'slug' => 'alpha', 'user_id' => $userA->id]);
        $postTwo = Post::create(['title' => 'Bravo', 'slug' => 'bravo', 'user_id' => $userA->id]);
        Post::create(['title' => 'Charlie', 'slug' => 'charlie', 'user_id' => $userB->id]);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['user' => (string) $userA->id],
        ]);

        $results = Post::query()
            ->applyJsonApi(ConfigurablePlainPostResource::class, $request)
            ->get();

        $this->assertSame(
            [$postOne->id, $postTwo->id],
            $results->pluck('id')->sort()->values()->all(),
        );
    }

    #[Test]
    public function plain_resource_can_include_snake_case_relationship_names_that_map_to_camel_case_model_methods(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.test']);
        $post = Post::create(['title' => 'Alpha', 'slug' => 'alpha', 'user_id' => $alice->id]);

        $request = Request::create('/posts', 'GET', [
            'include' => 'author_user',
        ]);

        $result = Post::query()
            ->applyJsonApi(SnakeCasePostResource::class, $request)
            ->firstOrFail();

        $this->assertTrue($result->relationLoaded('authorUser'));
        $author = $result->getRelation('authorUser');
        $this->assertInstanceOf(User::class, $author);
        $this->assertSame($alice->getKey(), $author->getKey());
        $this->assertFalse($result->relationLoaded('author_user'));
        $this->assertSame($post->getKey(), $result->getKey());
    }

    #[Test]
    public function plain_resource_can_filter_by_snake_case_relationship_names_that_map_to_camel_case_model_methods(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.test']);
        $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.test']);

        $alicePost = Post::create(['title' => 'Alpha', 'slug' => 'alpha', 'user_id' => $alice->id]);
        Post::create(['title' => 'Bravo', 'slug' => 'bravo', 'user_id' => $bob->id]);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['author_user.name' => 'Alice'],
        ]);

        $results = Post::query()
            ->applyJsonApi(SnakeCasePostResource::class, $request)
            ->get();

        $this->assertSame([$alicePost->id], $results->pluck('id')->all());
    }

    #[Test]
    public function plain_resource_can_sort_by_snake_case_relationship_names_that_map_to_camel_case_model_methods(): void
    {
        $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.test']);
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.test']);

        $alicePost = Post::create(['title' => 'Alpha', 'slug' => 'alpha', 'user_id' => $alice->id]);
        $bobPost = Post::create(['title' => 'Bravo', 'slug' => 'bravo', 'user_id' => $bob->id]);

        $request = Request::create('/posts', 'GET', [
            'sort' => 'author_user.name',
        ]);

        $results = Post::query()
            ->applyJsonApi(SnakeCasePostResource::class, $request)
            ->get();

        $this->assertSame(
            [$alicePost->id, $bobPost->id],
            $results->pluck('id')->all(),
        );
    }

    #[Test]
    public function configurable_plain_resource_uses_its_pagination_defaults_in_collection_macro(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET');

        $response = Post::query()->jsonApiCollection(ConfigurablePlainPostResource::class, $request);

        $this->assertCount(5, $response->resource->items());
    }

    #[Test]
    public function configurable_plain_resource_can_allow_unpaginated_collections(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET', ['page' => ['size' => '-1']]);

        $response = Post::query()
            ->jsonApiCollection(UnpaginatedConfigurablePlainPostResource::class, $request)
            ->response($request);

        $data = json_decode($response->getContent(), true);

        $this->assertCount(12, $data['data']);
        $this->assertArrayNotHasKey('links', $data);
        $this->assertArrayNotHasKey('meta', $data);
    }

    #[Test]
    public function configurable_resource_can_use_builtin_where_id_in_as_additional_filter(): void
    {
        $matchingUser = User::create(['name' => 'Alice', 'email' => 'alice@example.test']);
        User::create(['name' => 'Bob', 'email' => 'bob@example.test']);

        $request = Request::create('/users', 'GET', [
            'filter' => ['id' => (string) $matchingUser->getRouteKey()],
        ]);

        $results = User::query()
            ->applyJsonApi(ConfigurableUserResource::class, $request)
            ->get();

        $this->assertSame(
            [(string) $matchingUser->getRouteKey()],
            $results->pluck('id')->map(fn ($id) => (string) $id)->all(),
        );
    }
}
