<?php

namespace STS\ZipStream\Models;

use function GuzzleHttp\Psr7\stream_for;
use Psr\Http\Message\StreamInterface;
use STS\ZipStream\Exceptions\FilenameMissingException;
use STS\ZipStream\Exceptions\NotWritableException;

class TempFile extends File
{
    /**
     * For temp files (raw data provided) you MUST specify the zip path
     *
     * @return string
     * @throws FilenameMissingException
     */
    protected function getDefaultZipPath()
    {
        throw new FilenameMissingException();
    }

    /**
     * Note: strlen returns actual bytes used, mb_strlen returns number of characters. We want strlen.
     *
     * @return int
     */
    public function calculateFilesize(): int
    {
        return strlen($this->getSource());
    }

    /**
     * @return StreamInterface
     */
    protected function buildReadableStream(): StreamInterface
    {
        return stream_for($this->getSource());
    }

    /**
     * @return StreamInterface
     * @throws NotWritableException
     */
    protected function buildWritableStream(): StreamInterface
    {
        throw new NotWritableException();
    }
}