<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Http\Concerns;

use Illuminate\Http\Request;

trait HandlesJsonApiStore
{
    use FillsRelationships;

    public function store(Request $request): mixed
    {
        $resourceClass = $this->getResource();
        $modelClass = $this->getModel();

        $attributes = $request->input('data.attributes', []);
        $model = new $modelClass($attributes);

        $this->fillRelationshipsFromRequest($model, $request);

        if (method_exists($this, 'creating')) {
            $this->creating($model, $request);
        }

        $model->save();

        if (method_exists($this, 'created')) {
            $this->created($model, $request);
        }

        return (new $resourceClass($model))->response()->setStatusCode(201);
    }
}
