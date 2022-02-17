<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    // Default options for our archives
    'archive' => [
        'predict' => env('ZIPSTREAM_PREDICT_SIZE', true)
    ],

    // Default options for files added
    'file'    => [
        'method' => env('ZIPSTREAM_FILE_METHOD', 'store'),

        'deflate' => env('ZIPSTREAM_FILE_DEFLATE'),

        'sanitize' => env('ZIPSTREAM_FILE_SANITIZE', true)
    ],

    // AWS configs for S3 files
    'aws'     => [
        'credentials'             => [
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY')
        ],
        'version'                 => 'latest',
        'endpoint'                => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('ZIPSTREAM_AWS_PATH_STYLE_ENDPOINT', false),
        'region'                  => env('ZIPSTREAM_AWS_REGION', env('AWS_DEFAULT_REGION', 'us-east-1'))
    ],

    // https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials_anonymous.html
    'aws_anonymous_client' => env('AWS_ANONYMOUS', false)
];
