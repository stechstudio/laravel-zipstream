<?php

namespace STS\ZipStream;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Str;
use STS\ZipStream\Contracts\FileContract;
use STS\ZipStream\Events\ZipSizePredictionFailed;
use STS\ZipStream\Events\ZipStreamed;
use STS\ZipStream\Events\ZipStreaming;
use STS\ZipStream\Models\File;
use Psr\Http\Message\StreamInterface;
use ZipStream\Exception\OverflowException;
use ZipStream\ZipStream as BaseZipStream;
use ZipStream\Option\File as FileOptions;
use ZipStream\Option\Archive as ArchiveOptions;
use Symfony\Component\HttpFoundation\StreamedResponse;
use function GuzzleHttp\Psr7\stream_for;

class ZipStream extends BaseZipStream implements Responsable
{
    /** @var ArchiveOptions */
    protected $archiveOptions;

    /** @var FileOptions */
    protected $fileOptions;

    /** @var Collection */
    protected $queue;

    /** @var int */
    protected $bytesSent = 0;

    /** @var StreamInterface */
    protected $outputStream;

    /** @var StreamInterface */
    protected $cacheOutputStream;

    /** @var Collection  */
    protected $meta;

    /**
     * @param ArchiveOptions $archiveOptions
     * @param FileOptions $fileOptions
     */
    public function __construct(ArchiveOptions $archiveOptions, FileOptions $fileOptions)
    {
        parent::__construct(null, $archiveOptions);

        $this->archiveOptions = $archiveOptions;
        $this->fileOptions = $fileOptions;
        $this->queue = new Collection();
    }

    /**
     * @param string $name
     *
     * @param array $files
     *
     * @return ZipStream
     */
    public function create(?string $name = null, array $files = [])
    {
        $zip = (new self($this->archiveOptions, $this->fileOptions))->setName($name);

        foreach($files as $key => $value) {
            if(is_string($key)) {
                $zip->add($key, $value);
            } else {
                $zip->add($value);
            }
        }

        return $zip;
    }

    /**
     * @param $name
     *
     * @return $this
     */
    public function setName(string $name)
    {
        $this->output_name = $name;

        return $this;
    }

    /**
     * @param string|FileContract $source
     * @param string|null $zipPath
     *
     * @return $this
     */
    public function add($source, ?string $zipPath = null)
    {
        if (!$source instanceof FileContract) {
            $source = File::make($source, $zipPath);
        }

        // Don't add two files with the same zip path
        if (!$this->queue->has($source->getZipPath())) {
            $this->queue->put($source->getZipPath(), $source);
        }

        return $this;
    }

    /**
     * @param array $meta
     *
     * @return $this
     */
    public function setMeta(array $meta) {
        $this->meta = collect($meta);

        return $this;
    }

    /**
     * @return Collection
     */
    public function getMeta(): Collection
    {
        return $this->meta ?? collect();
    }

    /**
     * Builds the zip and writes to output stream
     *
     * @return int
     * @throws OverflowException
     */
    public function process(): int
    {
        event(new ZipStreaming($this));

        $this->queue->each(function (File $file) {
            $this->addFileFromPsr7Stream($file->getZipPath(), $file->getReadableStream(), $file->getOptions());
            $file->getReadableStream()->close();
        });

        $this->finish();
        $this->getOutputStream()->close();

        if ($this->cacheOutputStream) {
            $this->cacheOutputStream->close();
        }

        event(new ZipStreamed($this));

        if($this->canPredictZipSize() && $this->predictedZipSize() != $this->getFinalSize()) {
            event(new ZipSizePredictionFailed($this, $this->predictedZipSize(), $this->getFinalSize()));
        }

        return $this->getFinalSize();
    }

    /**
     * @return StreamedResponse
     */
    public function response(): StreamedResponse
    {
        return new StreamedResponse(function () {
            $this->process();
        }, 200, $this->getHeaders());
    }

    /**
     * @param Request $request
     *
     * @return StreamedResponse
     */
    public function toResponse($request)
    {
        return $this->response();
    }

    /**
     * @return array
     */
    protected function getHeaders(): array
    {
        return array_filter([
            'Content-Type'              => $this->opt->getContentType(),
            'Content-Disposition'       => $this->opt->getContentDisposition() . "; filename*=UTF-8''" . $this->getName(),
            'Pragma'                    => 'public',
            'Cache-Control'             => 'public, must-revalidate',
            'Content-Transfer-Encoding' => 'binary',
            'Content-Length'            => $this->canPredictZipSize() ? $this->predictedZipSize() : null
        ]);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        // Various different browsers dislike various characters here. Strip them all for safety.
        return rawurlencode(
            trim(str_replace(['"', "'", '\\', ';', "\n", "\r"], '', $this->output_name ?? "download.zip"))
        );
    }

    /**
     * @return int
     */
    public function getFinalSize(): int
    {
        return $this->bytesSent;
    }

    /**
     * @param string|FileContract $output
     *
     * @return ZipStream
     */
    public function cache($output)
    {
        if (!$output instanceof FileContract) {
            $output = File::make($output);
        }

        $this->cacheOutputStream = $output->getWritableStream();

        return $this;
    }

    /**
     * @return StreamInterface
     */
    protected function getOutputStream()
    {
        if (!$this->outputStream) {
            $this->outputStream = stream_for($this->archiveOptions->getOutputStream());
        }

        return $this->outputStream;
    }

    /**
     * @param string|FileContract $output
     *
     * @return int
     * @throws OverflowException
     */
    public function saveTo($output): int
    {
        if (!$output instanceof FileContract) {
            $output = File::make(Str::finish($output, "/") . $this->getName());
        }

        $this->outputStream = $output->getWritableStream();

        return $this->process();
    }

    /**
     * @param string $str
     */
    public function send(string $str): void
    {
        $this->getOutputStream()->write($str);

        if ($this->cacheOutputStream) {
            $this->cacheOutputStream->write($str);
        }

        $this->bytesSent += strlen($str);
    }

    /**
     * @return bool
     */
    public function canPredictZipSize()
    {
        return $this->queue->every->canPredictZipDataSize()
            && config('zipstream.archive.predict')
            && $this->queue->sum->getFilesize() < 0xFFFFFFFF;
    }

    /**
     * Stack Overflow FTW! http://stackoverflow.com/a/19380600/660694
     *
     * @return int
     */
    public function predictedZipSize(): int
    {
        return $this->canPredictZipSize()
            ? $this->queue->sum->predictZipDataSize() + 22
            : 0;
    }

    /**
     * @return string
     */
    public function getFingerprint(): string
    {
        return md5(
            // All file fingerprints, sorted and concatenated
            $this->queue->map->getFingerprint()->sort()->implode('')
            . $this->getName()
            . serialize($this->getMeta()->sort()->toArray())
        );
    }
}
