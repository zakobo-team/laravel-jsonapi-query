<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | These values are used when a resource does not define its own page
    | size limits. Per-resource values always take precedence.
    |
    */
    'pagination' => [
        /*
        |--------------------------------------------------------------------------
        | Default Page Size
        |--------------------------------------------------------------------------
        |
        | This value controls how many records are returned when a paginated
        | JSON:API request does not include page[size].
        |
        */
        'default_size' => 30,

        /*
        |--------------------------------------------------------------------------
        | Maximum Page Size
        |--------------------------------------------------------------------------
        |
        | This value controls the largest allowed page[size] for paginated
        | JSON:API requests.
        |
        */
        'max_size' => 100,
    ],
];
