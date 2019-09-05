# Streaming Zips with Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/stechstudio/laravel-zipstream.svg?style=flat-square)](https://packagist.org/packages/stechstudio/laravel-zipstream)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Quality Score](https://img.shields.io/scrutinizer/g/stechstudio/laravel-zipstream.svg?style=flat-square)](https://scrutinizer-ci.com/g/stechstudio/laravel-zipstream)

A fast and simple streaming zip file downloader for Laravel. 

- Builds zip files from local or S3 file sources, or any other PSR7 stream.
- Provides a direct download stream to your user. The zip download beings immediately even though the zip is still being created. No need to save the zip to disk first.
- Calculates the zip filesize up front for the `Content-Length` header. The user gets an accurate download time estimate in their browser.
- Built on top of the excellent [ZipStream-PHP](https://github.com/maennchen/ZipStream-PHP) library.

## Quickstart

#### 1. Install the package

```php
composer require stechstudio/laravel-zipstream
```
    
The service provider and facade will be automatically wired up.

#### 2. In a controller method call the `create` method on the `ZipStream` facade

```php
use ZipStream;

class ZipController {

    public function build()
    {
        return ZipStream::create("package.zip", [
            "/path/to/Some File.pdf",
            "/path/to/Export.xlsx"       
        ]);
    }
}
```

That's it! A `StreamedResponse` will be returned and the zip contents built and streamed out. The user's browser will begin downloading a `package.zip` file immediately.

## Customize the internal zip path for a file

By default any files you add will be stored in the root of the zip, with their original filenames. 

You can customize the filename and even create subfolders within the zip by providing your files array with key/value pairs:

```php
ZipStream::create("package.zip", [

    // Will be stored as `Some File.pdf` in the zip
    "/path/to/Some File.pdf",          
 
    // Will be stored as `Export.xlsx` in the zip
    "/path/to/data.xlsx" => 'Export.xlsx',
 
    // Will create a `log` subfolder in the zip and be stored as `log/details.txt`
    "/path/to/log.txt" => "log/details.txt"
 
]);
```

## Fluent usage

You can also provide your files one at a time:

```php
ZipStream::create("package.zip")
    ->add("/path/to/Some File.pdf")
    ->add("/path/to/data.xlsx", 'Export.xlsx')
    ->add("/path/to/log.txt", "log/details.txt");
```

## Support for S3

### Install AWS sdk and configure S3

You can stream files from S3 into your zip. 

1. Install the `aws/aws-sdk-php` package

2. Setup an AWS IAM user with `s3:GetObject` permission for the S3 bucket and objects you intend to zip up.

3. Store your credentials as `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, and `AWS_DEFAULT_REGION` in your .env file.

### Add S3 files to your zip

Provide `s3://` paths when creating the zip:

```php
ZipStream::create("package.zip")
    ->add("s3://bucket-name/path/to/object.pdf", "Something.pdf");
```

### Changing the region

If you need to pull files from an S3 region _other_ than what you have specified in `AWS_DEFAULT_REGION` you can make the `File` instance yourself and then set the region name.

```php
use ZipStream;
use STS\ZipStream\Models\File;

ZipStream::create("package.zip")
    ->add(File::make("s3://bucket-name/path/to/object.pdf", "Something.pdf")->setRegion("us-west-2"));
```

## Zip size prediction

By default this package attempts to predict the final zip size and sends a `Content-Length` header up front. This means users will see accurate progress on their download, even though the zip is being streamed out as it is created!

This only works if files are not compressed.

If you have issues with the zip size prediction you can disable it with `ZIPSTREAM_PREDICT_SIZE=false` in your .env file.

## Configure compression

By default this package uses _no_ compression. Why?

1) This makes building the zips super fast, and is light on your CPU
2) This makes it possible to predict the final zip size as mentioned above.

If you want to compress your zip files set `ZIPSTREAM_FILE_METHOD=deflate` in your .env file. Just realize this will disable the `Content-Length` header.

## Save Zip to disk

Even though the primary goal of this package is to enable zip downloads without saving to disk, there may be times you'd like to generate a zip on disk as well. And you might as well make use of this package to do so.

Use the `saveTo` method to write the entire zip to disk immediately. Note that this expects a folder path, the zip name will be appended.

```php
ZipStream::create("package.zip")
    // ... add files ...
    ->saveTo("/path/to/folder");
```

And yes, if you've properly setup and configured S3 you can even save to an S3 bucket/path.

```php
ZipStream::create("package.zip")
    // ... add files ...
    ->saveTo("s3://bucket-name/path/to/folder");
```

## Caching zip while still streaming download

What if you have a lot of users requesting the same zip payload? It might be nice to stream out the zip while _also_ caching it to disk for the future.

Use the `cache` method to provide a cache path. Note this should be the entire path including filename.

```php
ZipStream::create("package.zip")
    // ... add files ...
    ->cache("/path/to/folder/some-unique-cache-name.zip");
```

You might use an internal DB id for your cache name, so that the next time a user requests a zip download you can determine if one is already built and just hand it back.

## Events

- `STS\ZipStream\Events\ZipStreaming`: Dispatched when a new zip stream begins processing
- `STS\ZipStream\Events\ZipStreamed`: Dispatched when a zip finishes streaming
- `STS\ZipStream\Events\ZipSizePredictionFailed`: Fired if the predicted filesize doesn't match the final size. If you have filesize prediction enabled it's a good idea to listen for this event and log it, since that might mean the zip download failed or was corrupt for your user. 

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
