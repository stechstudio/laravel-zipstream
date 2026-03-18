<?php

use GuzzleHttp\Psr7\Utils;
use Illuminate\Filesystem\FilesystemAdapter;
use Orchestra\Testbench\TestCase;
use Psr\Http\Message\StreamInterface;
use STS\ZipStream\Factory;
use STS\ZipStream\Models\File;
use STS\ZipStream\Models\LocalFile;
use STS\ZipStream\Models\S3File;
use STS\ZipStream\OutputStream;
use STS\ZipStream\ZipStreamServiceProvider;

class StubCustomFile extends File
{
    public static function supports(string $source): bool
    {
        return str_starts_with($source, 'custom://');
    }

    public static function supportsDisk(FilesystemAdapter $disk): bool
    {
        return ($disk->getConfig()['driver'] ?? null) === 'custom';
    }

    public static function supportsWriting(): bool
    {
        return true;
    }

    public function calculateFilesize(): int
    {
        return 0;
    }

    protected function buildReadableStream(): StreamInterface
    {
        return Utils::streamFor('');
    }

    protected function buildWritableStream(): OutputStream
    {
        return new OutputStream(fopen('php://memory', 'wb'));
    }

    public function canPredictZipDataSize(): bool
    {
        return true;
    }
}

class FactoryTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ZipStreamServiceProvider::class,
        ];
    }

    public function testFactoryIsSingleton()
    {
        $a = app(Factory::class);
        $b = app(Factory::class);

        $this->assertSame($a, $b);
    }

    public function testMakeResolvesBuiltinTypes()
    {
        $factory = app(Factory::class);

        $this->assertInstanceOf(S3File::class, $factory->make('s3://bucket/key'));
        $this->assertInstanceOf(LocalFile::class, $factory->make('/tmp/foo'));
    }

    public function testExtendPrepends()
    {
        $factory = app(Factory::class);
        $factory->extend(StubCustomFile::class);

        $file = $factory->make('custom://something');

        $this->assertInstanceOf(StubCustomFile::class, $file);
        $this->assertEquals('custom://something', $file->getSource());
    }

    public function testExtendedTypeHasPriority()
    {
        $factory = app(Factory::class);

        // Before extending, an s3:// source resolves to S3File
        $this->assertInstanceOf(S3File::class, $factory->make('s3://bucket/key'));

        // StubCustomFile doesn't match s3://, so S3File still wins
        $factory->extend(StubCustomFile::class);
        $this->assertInstanceOf(S3File::class, $factory->make('s3://bucket/key'));

        // But custom:// is matched by the prepended type before any built-in
        $this->assertInstanceOf(StubCustomFile::class, $factory->make('custom://test'));
    }

    public function testMakeWriteableRespectsSupportsWriting()
    {
        $factory = app(Factory::class);
        $factory->extend(StubCustomFile::class);

        $file = $factory->makeWriteable('custom://something');

        $this->assertInstanceOf(StubCustomFile::class, $file);
    }

    public function testExtendViaBuilder()
    {
        $builder = app('zipstream.builder');
        $builder->extend(StubCustomFile::class);

        $file = File::make('custom://something');

        $this->assertInstanceOf(StubCustomFile::class, $file);
    }

    public function testMakeFromDiskWithExtendedType()
    {
        $factory = app(Factory::class);
        $factory->extend(StubCustomFile::class);

        config([
            'filesystems.disks.custom' => [
                'driver' => 'custom',
                'root' => '/tmp',
            ],
        ]);

        // This will fail because 'custom' isn't a real filesystem driver,
        // but we can test supportsDisk detection by using a mock
        $disk = $this->createStub(FilesystemAdapter::class);
        $disk->method('getConfig')->willReturn(['driver' => 'custom']);
        $disk->method('path')->willReturnCallback(fn($path) => "/tmp/$path");

        $file = $factory->makeFromDisk($disk, 'file.txt', 'archive/file.txt');

        $this->assertInstanceOf(StubCustomFile::class, $file);
        $this->assertEquals('/tmp/file.txt', $file->getSource());
        $this->assertEquals('archive/file.txt', $file->getZipPath());
    }
}
