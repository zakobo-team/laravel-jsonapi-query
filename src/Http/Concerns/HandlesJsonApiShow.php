<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Http\Concerns;

use Illuminate\Http\Request;

trait HandlesJsonApiShow
{
    public function show(Request $request, mixed $id): mixed
    {
        $resourceClass = $this->getResource();
        $modelClass = $this->getModel();

        $query = $modelClass::query();

        $model = $query->findOrFail($id);

        return new $resourceClass($model);
    }
}
