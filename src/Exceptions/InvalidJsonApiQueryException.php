<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Exceptions;

use RuntimeException;

class InvalidJsonApiQueryException extends RuntimeException
{
    public function __construct(
        protected readonly string $parameter,
        protected readonly string $detail,
    ) {
        parent::__construct($detail);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function errors(): array
    {
        return [$this->error()];
    }

    /**
     * @return array<string, mixed>
     */
    public function error(): array
    {
        return [
            'status' => '400',
            'title' => 'Bad Request',
            'detail' => $this->detail,
            'source' => [
                'parameter' => $this->parameter,
            ],
        ];
    }

    public function parameter(): string
    {
        return $this->parameter;
    }

    public function detail(): string
    {
        return $this->detail;
    }
}
