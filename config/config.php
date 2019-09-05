<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    // Default options for our archives
    'archive' => [
        'zip64' => env('ZIPSTREAM_ENABLE_ZIP64', true),

        'predict' => env('ZIPSTREAM_PREDICT_SIZE', true)
    ],

    // Default options for files added
    'file' => [
        'method' => env('ZIPSTREAM_FILE_METHOD', 'store'),

        'deflate' => env('ZIPSTREAM_FILE_DEFLATE'),
    ],

    // AWS configs for S3 files
    'aws' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('ZIPSTREAM_AWS_REGION', env('AWS_DEFAULT_REGION', 'us-east-1'))
    ]
];