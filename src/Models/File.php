<?php

namespace STS\ZipStream\Models;

use Psr\Http\Message\StreamInterface;
use Illuminate\Support\Str;
use STS\ZipStream\Contracts\FileContract;
use ZipStream\Option\File as FileOptions;
use ZipStream\Option\Method;

abstract class File implements FileContract
{
    /** @var string */
    protected $sourcePath;

    /** @var string */
    protected $zipPath;

    /** @var int */
    protected $filesize;

    /** @var StreamInterface */
    protected $readStream;

    /** @var StreamInterface */
    protected $writeStream;

    /** @var FileOptions */
    protected $options;

    /**
     * @param string $sourcePath
     * @param string|null $zipPath
     * @param FileOptions|null $options
     */
    public function __construct(string $sourcePath, ?string $zipPath = null, ?FileOptions $options = null)
    {
        $this->sourcePath = $sourcePath;

        if ($zipPath === null) {
            $this->zipPath = basename($sourcePath);
        } else {
            $this->zipPath = $zipPath;
        }

        $this->options = $options ?? resolve(FileOptions::class);
    }

    /**
     * @param string $source
     * @param string|null $zipPath
     *
     * @return FileContract
     */
    public static function make(string $source, ?string $zipPath = null)
    {
        return Str::startsWith($source, "s3://")
            ? new S3File($source, $zipPath)
            : new LocalFile($source, $zipPath);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return basename($this->getZipPath());
    }

    /**
     * @return string
     */
    public function getSourcePath(): string
    {
        return $this->sourcePath;
    }

    /**
     * @return string
     */
    public function getZipPath(): string
    {
        return Str::ascii(
            ltrim(
                preg_replace('|/{2,}|', '/',
                    $this->zipPath
                ),
                '/')
        );
    }

    /**
     * @return StreamInterface
     */
    public function getReadableStream(): StreamInterface
    {
        if(!$this->readStream) {
            $this->readStream = $this->buildReadableStream();
        }

        return $this->readStream;
    }

    /**
     * @return StreamInterface
     */
    public function getWritableStream(): StreamInterface
    {
        if(!$this->writeStream) {
            $this->writeStream = $this->buildWritableStream();
        }

        return $this->writeStream;
    }

    /**
     * @return StreamInterface
     */
    abstract protected function buildReadableStream(): StreamInterface;

    /**
     * @return StreamInterface
     */
    abstract protected function buildWritableStream(): StreamInterface;

    /**
     * @return int
     */
    public function getFilesize(): int
    {
        if(!$this->filesize) {
            $this->filesize = $this->calculateFilesize();
        }

        return $this->filesize;
    }

    /**
     * @return int
     */
    abstract protected function calculateFilesize(): int;

    /**
     * @return string
     */
    public function getFingerprint(): string
    {
        return md5($this->getSourcePath() . $this->getZipPath() . $this->getFilesize());
    }

    /**
     * @return FileOptions
     */
    public function getOptions(): FileOptions
    {
        return $this->options;
    }

    /**
     * return bool
     */
    public function canPredictZipDataSize(): bool
    {
        return $this->options->getMethod() == Method::STORE() && $this->getFilesize() < 0xFFFFFFFF;
    }

    /**
     * Based on http://stackoverflow.com/a/19380600/660694. Stack Overflow FTW!
     *
     * @return int
     */
    public function predictZipDataSize(): int
    {
        return 30 + 46 + (2 * strlen($this->getZipPath())) + $this->getFilesize();
    }
}