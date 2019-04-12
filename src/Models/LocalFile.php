<?php

namespace STS\ZipStream\Models;

use function GuzzleHttp\Psr7\stream_for;
use Psr\Http\Message\StreamInterface;

class LocalFile extends File
{
    /**
     * @return int
     */
    public function getFilesize(): int
    {
        return filesize($this->getSourcePath());
    }

    /**
     * @return StreamInterface
     */
    protected function buildReadableStream(): StreamInterface
    {
        return stream_for(fopen($this->getSourcePath(), 'r'));
    }

    /**
     * @return StreamInterface
     */
    protected function buildWritableStream(): StreamInterface
    {
        return stream_for(fopen($this->getSourcePath(), 'w'));
    }
}