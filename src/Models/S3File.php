<?php

namespace STS\ZipStream\Models;

use Aws;
use Aws\S3\S3Client;
use Aws\S3\S3UriParser;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use STS\ZipStream\OutputStream;

class S3File extends File
{
    protected string $region;

    protected S3Client $client;

    public function calculateFilesize(): int
    {
        // Use a separate, immediately discarded, stream to get the file size
        // to avoid the main stream from being opened prematurely then timing
        // out before the file contents can be streamed.
        $stream = $this->buildReadableStream();
        $size = $stream->getSize();
        $stream->close();

        return $size;
    }

    public function setS3Client(S3Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getS3Client(): S3Client
    {
        if (!isset($this->client)) {
            $this->client = app('zipstream.s3client');
        }

        return $this->client;
    }

    protected function buildReadableStream(): StreamInterface
    {
        $this->getS3Client()->registerStreamWrapper();

        return Utils::streamFor(fopen($this->getSource(), 'r'));
    }

    protected function buildWritableStream(): OutputStream
    {
        $this->getS3Client()->registerStreamWrapper();

        return new OutputStream(fopen($this->getSource(), 'w'));
    }

    public function canPredictZipDataSize(): bool
    {
        return true;
    }
}
