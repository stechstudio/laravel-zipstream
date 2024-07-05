<?php

namespace STS\ZipStream\Models;

use Illuminate\Support\Arr;
use Psr\Http\Message\StreamInterface;
use Illuminate\Support\Str;
use STS\ZipStream\Contracts\FileContract;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

abstract class File implements FileContract
{
    protected string $source;

    protected string $zipPath;

    protected array $options = [];

    protected int $filesize;

    protected StreamInterface $readStream;

    protected StreamInterface $writeStream;

    public function __construct(string $source, ?string $zipPath = null, array $options = [])
    {
        $this->source = $source;
        $this->zipPath = $zipPath ?? $this->getDefaultZipPath();
        $this->options = $options;
    }

    public static function make(string $source, ?string $zipPath = null): FileContract
    {
        if (Str::startsWith($source, "s3://")) {
            return new S3File($source, $zipPath);
        }

        if (Str::startsWith($source, "http") && filter_var($source, FILTER_VALIDATE_URL)) {
            return new HttpFile($source, $zipPath);
        }

        if (Str::startsWith($source, "/") || preg_match('/^\w:[\/\\\\]/', $source) || file_exists($source)) {
            return new LocalFile($source, $zipPath);
        }

        return new TempFile($source, $zipPath);
    }

    public static function makeWriteable(string $source, ?string $zipPath = null): S3File|LocalFile
    {
        if (Str::startsWith($source, "s3://")) {
            return new S3File($source, $zipPath);
        }

        return new LocalFile($source, $zipPath);
    }

    public function getName(): string
    {
        return basename($this->getZipPath());
    }

    public function getSource(): string
    {
        return $this->source;
    }

    protected function getDefaultZipPath()
    {
        return basename($this->getSource());
    }

    public function getZipPath(): string
    {
        $path = ltrim(preg_replace('|/{2,}|', '/', $this->zipPath), '/');

        return config('zipstream.ascii_filenames')
            ? Str::ascii($path)
            : $path;
    }

    public function getReadableStream(): StreamInterface
    {
        if (!isset($this->readStream)) {
            $this->readStream = $this->buildReadableStream();
        }

        return $this->readStream;
    }

    public function getWritableStream(): StreamInterface
    {
        if (!isset($this->writeStream)) {
            $this->writeStream = $this->buildWritableStream();
        }

        return $this->writeStream;
    }

    abstract protected function buildReadableStream(): StreamInterface;

    abstract protected function buildWritableStream(): StreamInterface;

    public function getFilesize(): int
    {
        if (!isset($this->filesize)) {
            $this->filesize = $this->calculateFilesize();
        }

        return $this->filesize;
    }

    public function setFilesize(int $filesize): self
    {
        $this->filesize = $filesize;

        return $this;
    }

    abstract public function canPredictZipDataSize(): bool;

    abstract protected function calculateFilesize(): int;

    public function getFingerprint(): string
    {
        return md5($this->getSource().$this->getZipPath().$this->getFilesize());
    }

    public function setOption($name, $value): static
    {
        $this->options[$name] = $value;

        return $this;
    }

    public function compressionMethod()
    {
        $default = config('zipstream.compression_method') === 'deflate'
            ? CompressionMethod::DEFLATE
            : CompressionMethod::STORE;

        return Arr::get($this->options, 'compressionMethod', $default);
    }

    public function prepare(ZipStream $zip): void
    {
        $zip->addFileFromCallback(
            fileName: $this->getZipPath(),
            callback: fn () => $this->getReadableStream(),
            compressionMethod: $this->compressionMethod(),
            exactSize: $this->getFilesize()
        );
    }
}
