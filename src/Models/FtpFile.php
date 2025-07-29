<?php

namespace STS\ZipStream\Models;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Str;
use Psr\Http\Message\StreamInterface;
use STS\ZipStream\OutputStream;

class FtpFile extends File
{
    protected FilesystemAdapter $disk;

    public static function makeFromDisk($disk, string $source, ?string $zipPath = null): static
    {
        $config = $disk->getConfig();

        $protocol = !empty($config['ssl']) ? 'ftps://' : 'ftp://';
        $host     = $config['host'] ?? '';

        $user = $config['username'] ?? null;
        $pass = $config['password'] ?? '';
        $userInfo = $user ? "$user:$pass@" : '';

        $root = $config['root'] ?? '';
        $root = $root ? Str::start($root, '/') : '';

        return new static($protocol . $userInfo . $host . $root .'/'. $source, $zipPath);
    }

    public function canPredictZipDataSize(): bool
    {
        return true;
    }

    public function calculateFilesize(): int
    {
        return stat($this->source)['size'];
    }

    protected function buildReadableStream(): StreamInterface
    {
        return Utils::streamFor($this->getStream('rb'));
    }

    protected function buildWritableStream(): OutputStream
    {
        return new OutputStream($this->getStream('wb'));
    }

    /**
     * @return resource
     */
    protected function getStream(string $mode)
    {
        return fopen($this->source, $mode, context: stream_context_create([
            'ftp' => [
                'overwrite' => true
            ]
        ]));
    }
}
