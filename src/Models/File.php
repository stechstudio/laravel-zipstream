<?php

namespace STS\ZipStream\Models;

use Psr\Http\Message\StreamInterface;
use Illuminate\Support\Str;
use STS\ZipStream\Contracts\FileContract;
use STS\ZipStream\ZipStream;
use STS\ZipStream\ZipStreamFile;
use ZipStream\Option\Archive as ArchiveOptions;
use ZipStream\Option\File as FileOptions;
use ZipStream\Option\Method;

abstract class File implements FileContract
{
    /** @var string */
    protected $source;

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
     * @param string $source
     * @param string|null $zipPath
     * @param FileOptions|null $options
     */
    public function __construct(string $source, ?string $zipPath = null, ?FileOptions $options = null)
    {
        $this->source = $source;
        $this->zipPath = $zipPath ?? $this->getDefaultZipPath();
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
        if (Str::startsWith($source, "s3://")) {
            return new S3File($source, $zipPath);
        }

        if (Str::startsWith($source, "http") && filter_var($source, FILTER_VALIDATE_URL)) {
            return new HttpFile($source, $zipPath);
        }

        if (Str::startsWith($source, "/") || preg_match('/^\w:\//', $source) || file_exists($source)) {
            return new LocalFile($source, $zipPath);
        }

        return new TempFile($source, $zipPath);
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
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @return string
     */
    protected function getDefaultZipPath()
    {
        return basename($this->getSource());
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
        if (!$this->readStream) {
            $this->readStream = $this->buildReadableStream();
        }

        return $this->readStream;
    }

    /**
     * @return StreamInterface
     */
    public function getWritableStream(): StreamInterface
    {
        if (!$this->writeStream) {
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
        if (!$this->filesize) {
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
        return md5($this->getSource() . $this->getZipPath() . $this->getFilesize());
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
        return $this->options->getMethod() == Method::STORE();
    }

    /**
     * @param ZipStream $zip
     *
     * @return ZipStreamFile
     */
    public function toZipStreamFile(ZipStream $zip): ZipStreamFile
    {
        return new ZipStreamFile($zip, $this);
    }
}
