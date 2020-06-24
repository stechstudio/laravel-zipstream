<?php

namespace STS\ZipStream\Models;

use Aws;
use Aws\S3\S3Client;
use Aws\S3\S3UriParser;
use function GuzzleHttp\Psr7\stream_for;
use Psr\Http\Message\StreamInterface;

class S3File extends File
{
    /** @var string */
    protected $region;

    /** @var S3Client */
    protected $client;

    /**
     * @param string $region
     *
     * @return $this
     */
    public function setRegion(?string $region = null)
    {
        $this->region = $region;

        return $this;
    }

    /**
     * @return int
     */
    public function calculateFilesize(): int
    {
        return $this->getS3Client()->headObject([
            'Bucket' => $this->getBucket(),
            'Key'    => $this->getKey()
        ])->get('ContentLength');
    }

    /**
     * @return string
     */
    public function getRegion(): string
    {
        return $this->region ?? config('zipstream.aws.region');
    }

    /**
     * @return S3Client
     */
    public function getS3Client(): S3Client
    {
        if (!$this->client) {
            $this->client = app('zipstream.s3client');
        }

        return $this->client;
    }

    /**
     * @return string
     */
    public function getBucket(): string
    {
        return parse_url($this->getSource(), PHP_URL_HOST);
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return ltrim(parse_url($this->getSource(), PHP_URL_PATH), "/");
    }

    /**
     * @return StreamInterface
     */
    protected function buildReadableStream(): StreamInterface
    {
        return $this->getS3Client()->getObject([
            'Bucket' => $this->getBucket(),
            'Key'    => $this->getKey()
        ])->get('Body');
    }

    /**
     * @return StreamInterface
     */
    protected function buildWritableStream(): StreamInterface
    {
        $this->getS3Client()->registerStreamWrapper();

        return stream_for(fopen($this->getSource(), 'w'));
    }
}
