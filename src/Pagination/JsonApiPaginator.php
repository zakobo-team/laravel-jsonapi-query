<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Pagination;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class JsonApiPaginator
{
    public function paginate(
        Builder $query,
        Request $request,
        int $defaultPageSize = 30,
        int $maxPageSize = 100,
    ): LengthAwarePaginator {
        $pageParams = $request->query('page', []);

        if (! is_array($pageParams)) {
            $pageParams = [];
        }

        $pageSize = $this->resolvePageSize($pageParams, $defaultPageSize, $maxPageSize);
        $pageNumber = $this->resolvePageNumber($pageParams);

        $paginator = $query->paginate(
            perPage: $pageSize,
            page: $pageNumber,
            pageName: 'page[number]',
        );

        $paginator->appends(['page[size]' => $pageSize]);

        return $paginator;
    }

    private function resolvePageSize(array $pageParams, int $defaultPageSize, int $maxPageSize): int
    {
        $size = isset($pageParams['size']) ? (int) $pageParams['size'] : 0;

        if ($size < 1) {
            return $defaultPageSize;
        }

        return min($size, $maxPageSize);
    }

    private function resolvePageNumber(array $pageParams): int
    {
        $number = isset($pageParams['number']) ? (int) $pageParams['number'] : 1;

        return max($number, 1);
    }
}
