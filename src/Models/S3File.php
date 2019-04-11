<?php

namespace STS\ZipStream\Models;

use Aws;
use Aws\S3\S3Client;
use Aws\S3\StreamWrapper;
use Aws\S3\S3UriParser;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class S3File extends ZipFile
{
    /** @var string */
    protected $region;

    /** @var S3Client\ */
    protected $client;

    /**
     * @param string $sourcePath
     * @param string|null $zipPath
     * @param string $region
     */
    public function __construct(string $sourcePath, ?string $zipPath = null, ?string $region = "us-east-1")
    {
        parent::__construct($sourcePath, $zipPath);
        $this->region = $region;

        if(!class_exists(S3Client::class, true)) {
            throw new RuntimeException("You must install the `aws/aws-sdk-php` for S3 file zipping support");
        }
    }

    /**
     * @return StreamInterface
     */
    public function getHandle(): StreamInterface
    {
        if(!$this->handle) {
            $this->handle = $this->getS3Client()->getObject([
                'Bucket' => $this->getParsedPath()['bucket'],
                'Key' => $this->getParsedPath()['key']
            ])->get('Body');
        }

        return $this->handle;
    }

    /**
     * @return int
     */
    public function getFilesize(): int
    {
        return $this->getS3Client()->headObject([
            'Bucket' => $this->getParsedPath()['bucket'],
            'Key' => $this->getParsedPath()['key']
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
            $this->client = new Aws\S3\S3Client([
                'region' => $this->getRegion(),
                'version' => '2006-03-01'
            ]);
        }

        return $this->client;
    }

    /**
     * @return array
     */
    public function getParsedPath(): array
    {
        return (new S3UriParser())->parse($this->getSourcePath());
    }
}