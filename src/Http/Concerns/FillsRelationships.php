<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Http\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait FillsRelationships
{
    protected function fillRelationshipsFromRequest(Model $model, Request $request): void
    {
        $relationships = $request->input('data.relationships', []);

        foreach ($relationships as $name => $relation) {
            $id = data_get($relation, 'data.id');

            if ($id === null) {
                continue;
            }

            $camelName = Str::camel($name);

            if (! method_exists($model, $camelName)) {
                continue;
            }

            $eloquentRelation = $model->{$camelName}();

            if ($eloquentRelation instanceof BelongsTo) {
                $model->{$eloquentRelation->getForeignKeyName()} = $id;
            }
        }
    }
}
