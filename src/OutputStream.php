<?php

namespace STS\ZipStream;

use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class OutputStream extends Stream
{
    protected ?StreamInterface $cached;

    public function cacheTo(StreamInterface $stream): self
    {
        $this->cached = $stream;

        return $this;
    }

    public function write($string): int
    {
        $result = parent::write($string);

        $this->cached()?->write($string);

        return $result;
    }

    public function close(): void
    {
        parent::close();

        $this->cached()?->close();
    }

    public function cached(): ?StreamInterface
    {
        return $this->cached;
    }
}