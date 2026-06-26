<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Paths
    |--------------------------------------------------------------------------
    |
    | Most applications have a single path containing view files. You may add
    | additional paths to support multi-tenant overrides or package layers.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This path is used to store compiled Blade templates. Using storage_path
    | avoids false values from realpath() when folders are not created yet.
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        storage_path('framework/views')
    ),

];
