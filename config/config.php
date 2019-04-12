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

    // Override default AWS configs if needed
    'aws' => [
        'region' => env('ZIPSTREAM_AWS_REGION', env('AWS_DEFAULT_REGION', 'us-east-1'))
    ]
];