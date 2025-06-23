<?php

namespace STS\ZipStream\Models;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Filesystem\FilesystemAdapter;
use Psr\Http\Message\StreamInterface;
use STS\ZipStream\Exceptions\DiskNotSpecified;
use STS\ZipStream\OutputStream;

class FtpFile extends File
{
    protected FilesystemAdapter $disk;

    public function setDisk(FilesystemAdapter $disk = null): static
    {
        $this->disk = $disk;
        return $this;
    }

    /**
     * @throws DiskNotSpecified
     */
    public function getDisk(): FilesystemAdapter
    {
        if (!isset($this->disk)) {
            throw new DiskNotSpecified('FTP disk not specified.');
        }

        return $this->disk;
    }

    public function canPredictZipDataSize(): bool
    {
        return true;
    }

    /**
     * @throws DiskNotSpecified
     */
    public function calculateFilesize(): int
    {
        return stat($this->getRemoteUrl())['size'];
    }

    /**
     * @throws DiskNotSpecified
     */
    protected function buildReadableStream(): StreamInterface
    {
        return Utils::streamFor($this->getStream('rb'));
    }

    /**
     * @throws DiskNotSpecified
     */
    protected function buildWritableStream(): OutputStream
    {
        return new OutputStream($this->getStream('wb'));
    }

    /**
     * @throws DiskNotSpecified
     */
    protected function getRemoteUrl(): string
    {
        $config = $this->getDisk()->getConfig();

        $protocol = !empty($config['ssl']) ? 'ftps://' : 'ftp://';
        $host     = $config['host'] ?? '';

        $user = $config['username'] ?? null;
        $pass = $config['password'] ?? '';
        $userInfo = $user ? "$user:$pass@" : '';

        $root = $config['root'] ?? '';
        $root = '/' . ($root ? trim($root, '/') . '/' : '');

        return $protocol.$userInfo.$host.$root.$this->getSource();
    }

    /**
     * @throws DiskNotSpecified
     * @return resource
     */
    protected function getStream(string $mode)
    {
        return fopen($this->getRemoteUrl(), $mode, context: stream_context_create([
            'ftp' => [
                'overwrite' => true
            ]
        ]));
    }
}
