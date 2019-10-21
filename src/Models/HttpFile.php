<?php

namespace STS\ZipStream\Models;

use function GuzzleHttp\Psr7\stream_for;
use Psr\Http\Message\StreamInterface;
use STS\ZipStream\Exceptions\NotWritableException;

class HttpFile extends File
{
    private const HEADER_CONTENT_LENGTH = 'content-length';
    /** @var array */
    private $headers;

    /**
     * @return int
     */
    public function calculateFilesize(): int
    {
        $headers = $this->getHeaders();
        return $headers[self::HEADER_CONTENT_LENGTH];
    }

    /**
     * @return StreamInterface
     */
    protected function buildReadableStream(): StreamInterface
    {
        return stream_for(fopen($this->getSource(), 'r'));
    }

    /**
     * @return StreamInterface
     * @throws NotWritableException
     */
    protected function buildWritableStream(): StreamInterface
    {
        throw new NotWritableException();
    }

    /**
     * @inheritdoc
     */
    public function canPredictZipDataSize(): bool
    {
        return array_key_exists(self::HEADER_CONTENT_LENGTH, $this->getHeaders()) &&
            parent::canPredictZipDataSize();
    }
    
    /**
     * @return array
     */
    protected function getHeaders(): array
    {
        if (!$this->headers) {
            $this->headers = array_change_key_case(get_headers($this->getSource(), 1));
        }
        return $this->headers;
    }
}
