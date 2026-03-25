# Laravel JSON:API Query

Query-time support for Laravel 13 JSON:API resources.

Laravel's `JsonApiResource` handles serialization, sparse fieldsets, includes, links, and meta. This package handles the part Laravel intentionally leaves to you: applying JSON:API query parameters to the Eloquent query itself.

That includes:

- filtering
- sorting
- include-aware eager loading
- constrained includes via `includeFilter`
- soft delete filters
- JSON:API pagination convenience

In most cases, `toAttributes()` and `toRelationships()` are enough.

## Installation

Add the repository to your application's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/zakobo-team/laravel-jsonapi-query"
        }
    ]
}
```

Then require the package:

```bash
composer require zakobo/laravel-jsonapi-query
```

The service provider is auto-discovered. If you want to publish the config:

```bash
php artisan vendor:publish --tag=jsonapi-query-config
```

## Quick Start

### 1. Define your resource

Start with the normal resource schema. In most cases, this is all you need:

```php
use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\JsonApiQueryResource;

class PostResource extends JsonApiQueryResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'title',
            'slug',
            'published_at',
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'author' => UserResource::class,
            'comments',
        ];
    }
}
```

From this, the package can infer:

- `filter[title]=...`
- `filter[published_at][gt]=...`
- `filter[author.name]=...`
- `filter[comments]=true`
- `sort=title,-published_at`
- `sort=author.name`
- `include=author,comments`

### 2. Apply JSON:API query parameters

`applyJsonApi()` is the primary entry point.

```php
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;

public function __invoke(Request $request)
{
    $posts = Post::query()
        ->applyJsonApi($request)
        ->where('tenant_id', $request->user()->tenant_id)
        ->get();

    return PostResource::collection($posts);
}
```

This works equally well in:

- controllers
- action classes
- application services
- DDD-style query handlers

### 3. Use the collection convenience macro when you want JSON:API pagination handled for you

If you want filtering, sorting, includes, and JSON:API pagination in one step:

```php
public function __invoke(Request $request)
{
    return Post::query()->jsonApiCollection($request);
}
```

You may still pass the resource explicitly:

```php
return Post::query()->jsonApiCollection(PostResource::class, $request);
```

## Conventions

### Attributes and relationships

The package derives its query schema from:

- `toAttributes()`
- `toRelationships()`

Direct same-name model columns are auto-filterable and auto-sortable.

Computed, transformed, or aliased attributes are output-only unless you explicitly wire them in through custom filters or sorts.

### Soft deletes

If the model uses `SoftDeletes`, these filters are available automatically:

- `filter[with-trashed]=true`
- `filter[only-trashed]=true`

No resource configuration is required.

### Pagination

`applyJsonApi()` does not paginate automatically. That is intentional.

Use it when you want full control over `get()`, `paginate()`, `cursorPaginate()`, or custom extraction flows.

Use `jsonApiCollection()` when you want the package to apply JSON:API pagination parameters for you.

## Filtering

### Attribute filters

```http
GET /posts?filter[title]=Hello
GET /posts?filter[published_at][gt]=2026-01-01
GET /posts?filter[votes][gte]=10&filter[votes][lte]=100
```

Supported operators:

- `eq`
- `gt`
- `gte`
- `lt`
- `lte`

### Relationship existence filters

```http
GET /posts?filter[comments]=true
GET /posts?filter[comments]=false
```

### Relationship filters

```http
GET /posts?filter[author.name]=Jane
GET /posts?filter[comments.author]=Jane
GET /posts?filter[author.country.name]=Denmark
```

Relationship paths use dot notation.

Relationship filters do not require the relationship to be present in `include`.

Use `filter[...]` when you want to change which primary models are returned.
Use `includeFilter[...]` when you want to constrain which related models are loaded for an included relationship.

If you apply multiple filters to the same relationship branch, they must match the same related record:

```http
GET /posts?filter[comments.author]=John&filter[comments.body]=Great
```

This means:

> Return posts that have a comment where `author = John` and `body = Great`.

It does not allow one related record to satisfy one condition and another related record to satisfy the other.

### Invalid filters fail loudly

Invalid query parameters return a JSON:API `400` response with a precise `source.parameter`.

Examples:

- unknown fields
- unsupported computed fields
- unsupported operators
- invalid filter structure

## Sorting

### Attribute sorting

```http
GET /posts?sort=title
GET /posts?sort=-published_at
GET /posts?sort=title,-published_at
```

### Relationship sorting

```http
GET /posts?sort=author.name
GET /posts?sort=-meta.seo_title
```

Auto relationship sorting is intentionally conservative:

- supported: single-hop `BelongsTo`
- supported: single-hop `HasOne`
- not supported automatically: `HasMany`, `BelongsToMany`, multi-hop sorts

If a relationship sort is valid, the package respects constraints defined on the relationship itself.

### Default sort

You may define a default sort on the resource:

```php
public ?string $defaultSort = '-published_at';
```

This supports:

- attributes
- valid relationship sorts
- custom additional sorts

## Includes

### Standard includes

```http
GET /posts?include=author,comments
GET /posts?include=author.country
```

Includes are applied at query time using eager loading.

### Include filters

Use `includeFilter` to constrain included relationships without changing the primary result set:

```http
GET /posts?include=comments&includeFilter[comments.author]=John
GET /posts?include=comments&includeFilter[comments.created_at][gt]=2026-01-01
GET /posts?include=author.country&includeFilter[author.country.name]=Denmark
```

Important:

- `filter[...]` changes which primary models are returned
- `includeFilter[...]` only changes which included related models are loaded

If an `includeFilter` targets a relationship path that was not requested in `include`, the request is rejected with a JSON:API `400` response.

## Model Domain Concepts As Relations

If something is a real domain concept, model it as a real Eloquent relationship.

For example, imagine an `Activity` with many `ActivityStatus` records. Every time the status changes, a new row is written to `activity_statusses`.

In that case, avoid solving "latest status" through computed attributes.

Prefer a dedicated relationship:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Activity extends Model
{
    public function activityStatuses(): HasMany
    {
        return $this->hasMany(ActivityStatus::class);
    }

    public function latestStatus(): HasOne
    {
        return $this->hasOne(ActivityStatus::class)->latestOfMany();
    }
}
```

This keeps the API explicit and queryable.

### Filter primary data by relationship data

If you only want activities that have a related status with `type = Completed`:

```http
GET /api/v4/activities?filter[activity-statusses.type]=Completed
```

This filters the primary `activities` collection.

If you also want the statuses included in the payload:

```http
GET /api/v4/activities?include=activity-statusses&filter[activity-statusses.type]=Completed
```

### Constrain included relationship records

If you want to return activities, but only include related statuses where `type = Completed`:

```http
GET /api/v4/activities?include=activity-statusses&includeFilter[activity-statusses.type]=Completed
```

This does **not** filter the primary `activities` collection. It only constrains the included relationship records.

### Prefer explicit relations over computed attributes

If the business concept is "current status" or "latest status", prefer:

```http
GET /api/v4/activities?include=latest-status
GET /api/v4/activities?filter[latest-status.type]=Completed
```

instead of inventing a computed attribute or a custom pseudo-filter like `includeFilter=latest-statusses`.

As a rule of thumb:

- use `filter[...]` to decide which primary records are returned
- use `includeFilter[...]` to decide which related records are included
- use a dedicated relationship when the concept has a real domain meaning such as `latestStatus()` or `currentStatus()`

## Custom Filters and Sorts

Only configure what breaks the normal convention.

Typical examples:

- computed fields
- aliases
- scope-based filters
- custom subquery sorts

### Resource configuration

```php
use App\Filters\PopularFilter;
use App\Sorting\LatestCommentSort;
use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\JsonApiQueryResource;

class PostResource extends JsonApiQueryResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'title',
            'slug',
            'published_at',
            'comment_count' => fn () => $this->comments_count,
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'author' => UserResource::class,
            'comments',
        ];
    }

    public array $excludedFromFilter = ['comment_count'];

    public array $excludedFromSorting = ['comment_count'];

    public array $additionalFilters = [
        'popular' => PopularFilter::class,
    ];

    public array $additionalSorts = [
        'latest-comment' => LatestCommentSort::class,
    ];
}
```

### Custom filters

Custom filters implement `Zakobo\JsonApiQuery\Filters\Contracts\Filter`.

They are resolved through Laravel's container, so constructor injection works as expected.

```php
use Illuminate\Database\Eloquent\Builder;
use Zakobo\JsonApiQuery\Filters\Contracts\Filter;

class PopularFilter implements Filter
{
    public function __construct(
        protected readonly string $key,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function apply(Builder $query, mixed $value): void
    {
        if ($value) {
            $query->popular();
        }
    }
}
```

### Custom sorts

Custom sorts implement `Zakobo\JsonApiQuery\Sorting\Contracts\Sort`.

They are also resolved through Laravel's container.

```php
use Illuminate\Database\Eloquent\Builder;
use Zakobo\JsonApiQuery\Sorting\Contracts\Sort;

class LatestCommentSort implements Sort
{
    public function __construct(
        protected readonly string $key,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function apply(Builder $query, string $direction): void
    {
        $query->orderByLatestComment($direction);
    }
}
```

## Resource Options

These are the main package-specific options:

```php
public array $excludedFromFilter = [];
public array $excludedFromSorting = [];
public array $additionalFilters = [];
public array $additionalSorts = [];
public ?string $defaultSort = null;
public ?int $defaultPageSize = null;
public ?int $maxPageSize = null;
```

If you do not need one of these, leave it out.

## Error Handling

Register the JSON:API exception renderer in `bootstrap/app.php`:

```php
use Zakobo\JsonApiQuery\Exceptions\JsonApiExceptionRenderer;

$exceptions->renderable(JsonApiExceptionRenderer::render());
```

This will turn invalid query parameters into JSON:API error responses with:

- `status`
- `title`
- `detail`
- `source.parameter`

## Configuration

```php
return [
    'pagination' => [
        'default_size' => 30,
        'max_size' => 100,
    ],
];
```

These values are used by `jsonApiCollection()`. Resource-level defaults override them.
