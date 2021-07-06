<?php

namespace STS\ZipStream\Models;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

class LocalFile extends File
{
    /**
     * @return int
     */
    public function calculateFilesize(): int
    {
        return filesize($this->getSource());
    }

    /**
     * @return StreamInterface
     */
    protected function buildReadableStream(): StreamInterface
    {
        return Utils::streamFor(fopen($this->getSource(), 'r'));
    }

    /**
     * @return StreamInterface
     */
    protected function buildWritableStream(): StreamInterface
    {
        if(!is_dir(dirname($this->getSource()))) {
            mkdir(dirname($this->getSource()), 0777, true);
        }

        return Utils::streamFor(fopen($this->getSource(), 'w'));
    }
}
