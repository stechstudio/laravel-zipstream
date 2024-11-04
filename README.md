# Streaming Zips with Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/stechstudio/laravel-zipstream.svg?style=flat-square)](https://packagist.org/packages/stechstudio/laravel-zipstream)
[![Total Downloads](https://img.shields.io/packagist/dt/stechstudio/laravel-zipstream.svg?style=flat-square)](https://packagist.org/packages/stechstudio/laravel-zipstream)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
![Build Status](https://img.shields.io/endpoint?url=https://app.chipperci.com/projects/5cc95e3c-628f-48c6-815c-1f16621c9514/status/master&style=flat-square)

A fast and simple streaming zip file downloader for Laravel. 

- Builds zip files from local or S3 file sources, or any other PSR7 stream.
- Provides a direct download stream to your user. The zip download begins immediately even though the zip is still being created. No need to save the zip to disk first.
- Calculates the zip filesize up front for the `Content-Length` header. The user gets an accurate download time estimate in their browser.
- Built on top of the excellent [ZipStream-PHP](https://github.com/maennchen/ZipStream-PHP) library.

## Upgrading

Upgrading from version 4? Make sure to look at the release notes for version 5. There are some breaking changes.

https://github.com/stechstudio/laravel-zipstream/releases/tag/5.0

## Quickstart

#### 1. Install the package

```php
composer require stechstudio/laravel-zipstream
```
    
The service provider and facade will be automatically wired up.

#### 2. In a controller method call the `create` method on the `Zip` facade

```php
use STS\ZipStream\Facades\Zip;

class ZipController {

    public function build()
    {
        return Zip::create("package.zip", [
            "/path/to/Some File.pdf",
            "/path/to/Export.xlsx"       
        ]);
    }
}
```

That's it! A `StreamedResponse` will be returned and the zip contents built and streamed out. The user's browser will begin downloading a `package.zip` file immediately.

## Customize the internal zip path for a file

By default, any files you add will be stored in the root of the zip, with their original filenames. 

You can customize the filename and even create sub-folders within the zip by providing your files array with key/value pairs:

```php
Zip::create("package.zip", [

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
Zip::create("package.zip")
    ->add("/path/to/Some File.pdf")
    ->add("/path/to/data.xlsx", 'Export.xlsx')
    ->add("/path/to/log.txt", "log/details.txt");
```

## Add HTTP file sources

You can add HTTP URLs as the source filepath. Note that zip filesize can only be calculated up front if the HTTP source provides a `Content-Length` header, not all URLs do. 

```php
Zip::create("package.zip")
    ->add("https://...", "myfile.pdf");
```

## Add raw file data

You can provide raw data instead of a filepath:

```php
Zip::create("package.zip")
    ->addRaw("...file contents...", "hello.txt");
```

## Add from storage disk

You can add files from a storage disk. Use `addFromDisk` and provide the disk name or disk instance as the first argument:

```php
Zip::create("package.zip")
    ->addFromDisk("local", "file.txt", "My File.txt")
    ->addFromDisk(Storage::disk("tmp"), "important.txt")
```

## Support for S3

### Install AWS sdk and configure S3

You can stream files from S3 into your zip. 

1. Install the `aws/aws-sdk-php` package

2. Set up an AWS IAM user with `s3:GetObject` permission for the S3 bucket and objects you intend to zip up.

3. Store your credentials as `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, and `AWS_DEFAULT_REGION` in your .env file.

### Add S3 files to your zip

Provide `s3://` paths when creating the zip:

```php
Zip::create("package.zip")
    ->add("s3://bucket-name/path/to/object.pdf", "Something.pdf");
```

By default, this package will try to create an S3 client using the same .env file credentials that Laravel uses. If needed, you can wire up a custom S3 client to the `zipstream.s3client` container key. Or you can even pass in your own S3 client when adding a file to the zip. To do this, you'll need to create an `S3File` model instance yourself so that you can provide the client, like this:

```php
use STS\ZipStream\Models\S3File;

// Create your own client however necessary
$s3 = new Aws\S3\S3Client();

Zip::create("package.zip")->add(
    S3File::make("s3://bucket-name/path/to/object.pdf")->setS3Client($s3)
);
```

Instead of specifying an absolute `s3://` path, you can use `addFromDisk` and specify a disk that uses the `s3` driver:

```php
Zip::create("package.zip")
    ->addFromDisk("s3", "object.pdf", "Something.pdf");
```

In this case the S3 client from the storage disk will be used. 

## Specify your own filesizes

It can be expensive retrieving filesizes for some file sources such as S3 or HTTP. These require dedicated calls, and can add up to a lot of time if you are zipping up many files. If you store filesizes in your database and have them available, you can drastically improve performance by providing filesizes when you add files. You'll need to make your own File models instead of adding paths directly to the zip.

Let's say you have a collection of Eloquent `$files`, are looping through and building a zip. If you have a `filesize` attribute available, it would look something like this:

```php
use STS\ZipStream\Models\File;

// Retrieve file records from the database
$files = ...;

$zip = Zip::create("package.zip");

foreach($files AS $file) {
    $zip->add(
        File::make($file->path, $file->name)->setFilesize($file->size)
    );
}
```

Or if you are adding from an S3 disk:

```php
$zip->add(
    File::makeFromDisk('s3', $file->key, $file->name)->setFilesize($file->size)
);
````

## Zip size prediction

By default, this package attempts to predict the final zip size and sends a `Content-Length` header up front. This means users will see accurate progress on their download, even though the zip is being streamed out as it is created!

This only works if files are not compressed.

If you have issues with the zip size prediction you can disable it with `ZIPSTREAM_PREDICT_SIZE=false` in your .env file.

## Configure compression

By default, this package uses _no_ compression. Why?

1) This makes building the zips super fast, and is light on your CPU
2) This makes it possible to predict the final zip size as mentioned above.

If you want to compress your zip files set `ZIPSTREAM_COMPRESSION_METHOD=deflate` in your .env file. Just realize this will disable the `Content-Length` header.

## Configure conflict strategy

If two or more files are added to the zip with the same zip path, you can use `ZIPSTREAM_CONFLICT_STRATEGY` to configure how the conflict is handled:

- `ZIPSTREAM_CONFLICT_STRATEGY=skip`: Keep the initial file, skip adding the conflicting file (default)
- `ZIPSTREAM_CONFLICT_STRATEGY=replace`: Keep the latest file, overwrite previous files at the same path
- `ZIPSTREAM_CONFLICT_STRATEGY=rename`: Append a number to the conflicting file name, e.g. `file.txt` becomes `file_1.txt`

Note: filenames are compared case-insensitive. `FILE.txt` and `file.TXT` are considered conflicting. If you are working only on a case-sensitive filesystem you can set `ZIPSTREAM_CASE_INSENSITIVE_CONFLICTS=false`. Don't do this if you have Windows users opening your zips!

## Save Zip to disk

Even though the primary goal of this package is to enable zip downloads without saving to disk, there may be times you'd like to generate a zip on disk as well. And you might as well make use of this package to do so.

Use the `saveTo` method to write the entire zip to disk immediately. Note that this expects a folder path, the zip name will be appended.

```php
Zip::create("package.zip")
    // ... add files ...
    ->saveTo("/path/to/folder");
```

And yes, if you've properly setup and configured S3 you can even save to an S3 bucket/path.

```php
Zip::create("package.zip")
    // ... add files ...
    ->saveTo("s3://bucket-name/path/to/folder");
```

Or you can save to a disk:

```php
Zip::create("package.zip")
    // ... add files ...
    ->saveToDisk("s3", "folder");
```

## Caching zip while still streaming download

What if you have a lot of users requesting the same zip payload? It might be nice to stream out the zip while _also_ caching it to disk for the future.

Use the `cache` method to provide a cache path. Note this should be the entire path including filename.

```php
Zip::create("package.zip")
    // ... add files ...
    ->cache("/path/to/folder/some-unique-cache-name.zip");
```

Or you can cache to a disk:

```php
Zip::create("package.zip")
    // ... add files ...
    ->cacheToDisk("s3", "folder/some-unique-cache-name.zip");
```

You might use an internal DB id for your cache name, so that the next time a user requests a zip download you can determine if one is already built and just hand it back.

## Events

- `STS\ZipStream\Events\ZipStreaming`: Dispatched when a new zip stream begins processing
- `STS\ZipStream\Events\ZipStreamed`: Dispatched when a zip finishes streaming 

## Filename sanitization

By default, this package will try to translate any non-ascii character in filename or folder's name to ascii. For example, if your filename is `中文_にほんご_Ч_Ɯ_☺_someascii.txt`. It will become `__C___someascii.txt` using Laravel's `Str::ascii($path)`.

If you need to preserve non-ascii characters, you can disable this feature with an `.env` setting:

```env
ZIPSTREAM_ASCII_FILENAMES=false
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
