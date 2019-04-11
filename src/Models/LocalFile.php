<?php

namespace STS\ZipStream\Models;

use function GuzzleHttp\Psr7\stream_for;
use Psr\Http\Message\StreamInterface;

class LocalFile extends ZipFile
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
    public function getHandle(): StreamInterface
    {
        if(!$this->handle) {
            $this->handle = stream_for(fopen($this->getSourcePath(), 'r'));
        }

        return $this->handle;
    }
}