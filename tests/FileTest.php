<?php

namespace STS\ZipStream\Tests;

use Orchestra\Testbench\TestCase;
use STS\ZipStream\Models\File;
use STS\ZipStream\Models\LocalFile;
use STS\ZipStream\Models\S3File;
use STS\ZipStream\Models\TempFile;
use STS\ZipStream\ZipStreamServiceProvider;

class FileTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ZipStreamServiceProvider::class,
        ];
    }

    public function testMake()
    {
        $this->assertInstanceOf(S3File::class, File::make('s3://bucket/key'));
        $this->assertInstanceOf(LocalFile::class, File::make('/dev/null'));
        $this->assertInstanceOf(LocalFile::class, File::make('/tmp/foobar'));
        $this->assertInstanceOf(LocalFile::class, File::make('C:/foo/bar'));
        $this->assertInstanceOf(LocalFile::class, File::make('D:\\foo\\bar'));
        $this->assertInstanceOf(TempFile::class, File::make("raw contents", "filename.txt"));
    }

    public function testMakeWriteable()
    {
        $this->assertInstanceOf(S3File::class, File::makeWriteable('s3://bucket/key'));
        $this->assertInstanceOf(LocalFile::class, File::makeWriteable('/tmp/foobar'));
        $this->assertInstanceOf(LocalFile::class, File::makeWriteable("C:/"));
        $this->assertInstanceOf(LocalFile::class, File::makeWriteable("C:\\"));
    }

    public function testLocalFile()
    {
        $filename = md5(microtime());
        file_put_contents("/tmp/$filename", "hi there");

        $file = new LocalFile("/tmp/$filename", "test.txt");

        $this->assertEquals(8, $file->getFilesize());
        $this->assertEquals("hi there", $file->getReadableStream()->getContents());
        $this->assertEquals("test.txt", $file->getZipPath());
    }

    public function testTempFile()
    {
        $file = new TempFile("hi there", "test.txt");

        $this->assertEquals(8, $file->getFilesize());
        $this->assertEquals("hi there", $file->getReadableStream()->getContents());
        $this->assertEquals("test.txt", $file->getZipPath());
    }

    public function testSettingFilesize()
    {
        $file = new TempFile("hi there", "test.txt");
        $file->setFilesize(12345);

        $this->assertEquals(12345, $file->getFilesize());
    }

    public function testSanitizeFilename()
    {
        // Default is to sanitize the filename
        $file = new TempFile("hi there", "ϩtrÂͶğƎ♡.txt");
        $this->assertEquals('trAg.txt', $file->getZipPath());

        config(['zipstream.file.sanitize' => false]);
        $this->assertEquals("ϩtrÂͶğƎ♡.txt", $file->getZipPath());
    }
}
