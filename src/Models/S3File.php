<?php

namespace STS\ZipStream\Models;

use Aws;
use Aws\S3\S3Client;
use Aws\S3\S3UriParser;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

class S3File extends File
{
    /** @var string */
    protected $region;

    /** @var S3Client */
    protected $client;

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
     * @param S3Client $client
     *
     * @return mixed
     */
    public function setS3Client(S3Client $client)
    {
        $this->client = $client;

        return $this;
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
        return ltrim(substr($this->getSource(), strlen('s3://'.$this->getBucket())), '/');
    }

    /**
     * @return StreamInterface
     */
    protected function buildReadableStream(): StreamInterface
    {
        $this->getS3Client()->registerStreamWrapper();

        return Utils::streamFor(fopen($this->getSource(), 'r'));
    }

    /**
     * @return StreamInterface
     */
    protected function buildWritableStream(): StreamInterface
    {
        $this->getS3Client()->registerStreamWrapper();

        return Utils::streamFor(fopen($this->getSource(), 'w'));
    }
}
