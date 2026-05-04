<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature\Documentation;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Documentation\JsonApiQueryDocumentationFactory;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\UnpaginatedPostResource;
use Zakobo\JsonApiQuery\Tests\TestCase;

class JsonApiQueryDocumentationFactoryTest extends TestCase
{
    #[Test]
    public function it_documents_the_runtime_json_api_query_contract_for_a_model_and_resource(): void
    {
        $documentation = app(JsonApiQueryDocumentationFactory::class)
            ->for(new Post, PostResource::class, Request::create('/'));

        $this->assertSame(Post::class, $documentation->modelClass);
        $this->assertSame(PostResource::class, $documentation->resourceClass);
        $this->assertSame('posts', $documentation->resourceType);

        $this->assertContains('title', $documentation->filterFields);
        $this->assertContains('created_at', $documentation->filterFields);
        $this->assertContains('with-trashed', $documentation->filterFields);
        $this->assertContains('only-trashed', $documentation->filterFields);
        $this->assertContains('popular', $documentation->filterFields);
        $this->assertContains('comments', $documentation->filterFields);
        $this->assertContains('comments.author', $documentation->filterFields);
        $this->assertContains('user.email', $documentation->filterFields);
        $this->assertContains('meta.seo_title', $documentation->filterFields);
        $this->assertNotContains('computed_score', $documentation->filterFields);
        $this->assertNotContains('body', $documentation->filterFields);

        $this->assertContains('title', $documentation->sortFields);
        $this->assertContains('created_at', $documentation->sortFields);
        $this->assertContains('latest-comment', $documentation->sortFields);
        $this->assertContains('user.name', $documentation->sortFields);
        $this->assertContains('meta.seo_title', $documentation->sortFields);
        $this->assertNotContains('comments.author', $documentation->sortFields);
        $this->assertNotContains('computed_score', $documentation->sortFields);

        $this->assertContains('comments', $documentation->includePaths);
        $this->assertContains('comments.post', $documentation->includePaths);
        $this->assertContains('user.country', $documentation->includePaths);
        $this->assertContains('meta.post', $documentation->includePaths);
        $this->assertNotContains('comments.post.comments', $documentation->includePaths);
        $this->assertNotContains('user.posts.user', $documentation->includePaths);

        $this->assertContains('comments.author', $documentation->includeFilterFields);
        $this->assertContains('comments.body', $documentation->includeFilterFields);
        $this->assertContains('comments.created_at', $documentation->includeFilterFields);
        $this->assertContains('user.country.name', $documentation->includeFilterFields);
        $this->assertContains('meta.post.title', $documentation->includeFilterFields);
        $this->assertNotContains('computed_score', $documentation->includeFilterFields);

        $this->assertSame([
            'title',
            'slug',
            'votes',
            'published',
            'created_at',
            'computed_score',
        ], $documentation->fieldsets['posts']);
        $this->assertSame(['author', 'body', 'created_at'], $documentation->fieldsets['comments']);

        $this->assertSame(15, $documentation->defaultPageSize);
        $this->assertSame(50, $documentation->maxPageSize);
        $this->assertFalse($documentation->allowUnpaginated);
    }

    #[Test]
    public function it_documents_pagination_defaults_and_unpaginated_support_from_resource_configuration(): void
    {
        $documentation = app(JsonApiQueryDocumentationFactory::class)
            ->for(new Post, UnpaginatedPostResource::class);

        $this->assertSame(15, $documentation->defaultPageSize);
        $this->assertSame(50, $documentation->maxPageSize);
        $this->assertTrue($documentation->allowUnpaginated);
    }
}
