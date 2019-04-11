<?php

namespace STS\ZipStream\Models;

use Illuminate\Support\Str;
use Psr\Http\Message\StreamInterface;

abstract class ZipFile
{
    /** @var string */
    protected $sourcePath;

    /** @var string */
    protected $zipPath;

    /** @var StreamInterface */
    protected $handle;

    /**
     * @param string $sourcePath
     * @param string|null $zipPath
     */
    public function __construct(string $sourcePath, ?string $zipPath = null)
    {
        $this->sourcePath = $sourcePath;

        if ($zipPath == null) {
            $this->zipPath = basename($sourcePath);
        } else {
            $this->zipPath = $zipPath;
        }
    }

    /**
     * @param string $sourcePath
     * @param string|null $zipPath
     *
     * @return LocalFile
     */
    public static function fromLocalPath(string $sourcePath, ?string $zipPath = null)
    {
        return new LocalFile($sourcePath, $zipPath);
    }

    /**
     * @param string $sourcePath
     * @param string|null $zipPath
     * @param string|null $region
     *
     * @return S3File
     */
    public static function fromS3Path(string $sourcePath, ?string $zipPath = null, ?string $region = null)
    {
        return new S3File($sourcePath, $zipPath, $region);
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
     * @return int
     */
    abstract public function getFilesize(): int;

    /**
     * @return StreamInterface
     */
    abstract public function getHandle(): StreamInterface;

    /**
     * @return void
     */
    public function closeHandle(): void
    {
        $this->handle->close();
    }

    /**
     * @return string
     */
    public function getFingerprint(): string
    {
        return md5($this->getSourcePath() . $this->getZipPath() . $this->getFilesize());
    }
}