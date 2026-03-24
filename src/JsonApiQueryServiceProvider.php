<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery;

use Illuminate\Support\ServiceProvider;

class JsonApiQueryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/jsonapi-query.php', 'jsonapi-query');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/jsonapi-query.php' => config_path('jsonapi-query.php'),
        ], 'jsonapi-query-config');
    }
}
