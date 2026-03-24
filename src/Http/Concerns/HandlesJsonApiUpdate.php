<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Http\Concerns;

use Illuminate\Http\Request;

trait HandlesJsonApiUpdate
{
    use FillsRelationships;

    public function update(Request $request, mixed $id): mixed
    {
        $resourceClass = $this->getResource();
        $modelClass = $this->getModel();

        $model = $modelClass::findOrFail($id);

        $attributes = $request->input('data.attributes', []);
        $model->fill($attributes);

        $this->fillRelationshipsFromRequest($model, $request);

        if (method_exists($this, 'updating')) {
            $this->updating($model, $request);
        }

        $model->save();

        if (method_exists($this, 'updated')) {
            $this->updated($model, $request);
        }

        return new $resourceClass($model);
    }
}
