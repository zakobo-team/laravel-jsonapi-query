<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Zakobo\JsonApiQuery\Includes\IncludeApplier;
use Zakobo\JsonApiQuery\Pagination\JsonApiPaginator;
use Zakobo\JsonApiQuery\Schema\ResourceSchemaFactory;
use Zakobo\JsonApiQuery\Sorting\SortApplier;
use Zakobo\JsonApiQuery\Validation\QueryValidator;

class JsonApiQueryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/jsonapi-query.php', 'jsonapi-query');

        $this->app->singleton(ResourceSchemaFactory::class);
        $this->app->singleton(QueryValidator::class);
        $this->app->singleton(SortApplier::class);
        $this->app->singleton(IncludeApplier::class);
        $this->app->singleton(JsonApiPaginator::class);
        $this->app->singleton(JsonApiQueryBuilder::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/jsonapi-query.php' => config_path('jsonapi-query.php'),
        ], 'jsonapi-query-config');

        $this->registerBuilderMacros();
    }

    private function registerBuilderMacros(): void
    {
        $resolveMacroArguments = static function (array $arguments): array {
            if (count($arguments) === 1 && $arguments[0] instanceof Request) {
                return [null, $arguments[0]];
            }

            if (
                count($arguments) === 2
                && is_string($arguments[0])
                && $arguments[1] instanceof Request
            ) {
                return [$arguments[0], $arguments[1]];
            }

            throw new InvalidArgumentException('Expected (Request $request) or (string $resourceClass, Request $request).');
        };

        Builder::macro('jsonApiCollection', function (...$arguments) use ($resolveMacroArguments) {
            /** @var Builder $this */
            [$resourceClass, $request] = $resolveMacroArguments($arguments);

            return app(JsonApiQueryBuilder::class)->collection($this, $request, $resourceClass);
        });

        Builder::macro('applyJsonApi', function (...$arguments) use ($resolveMacroArguments) {
            /** @var Builder $this */
            [$resourceClass, $request] = $resolveMacroArguments($arguments);

            return app(JsonApiQueryBuilder::class)->apply($this, $request, $resourceClass);
        });
    }
}
