<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Http\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

trait HandlesJsonApiDestroy
{
    public function destroy(Request $request, mixed $id): Response
    {
        $modelClass = $this->getModel();

        $model = $modelClass::findOrFail($id);

        if (method_exists($this, 'deleting')) {
            $this->deleting($model, $request);
        }

        $model->delete();

        if (method_exists($this, 'deleted')) {
            $this->deleted($model, $request);
        }

        return response()->noContent();
    }
}
