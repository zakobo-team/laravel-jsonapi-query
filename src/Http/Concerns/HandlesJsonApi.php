<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Http\Concerns;

trait HandlesJsonApi
{
    use HandlesJsonApiDestroy;
    use HandlesJsonApiIndex;
    use HandlesJsonApiShow;
    use HandlesJsonApiStore;
    use HandlesJsonApiUpdate;
}
