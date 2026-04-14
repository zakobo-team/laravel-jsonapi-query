<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $url
 * @property string $imageable_type
 * @property int $imageable_id
 */
class Image extends Model
{
    protected $guarded = [];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
