<?php

namespace STS\ZipStream;

use GuzzleHttp\Psr7\Utils;
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
use STS\ZipStream\Models\TempFile;
use ZipStream\Exception\OverflowException;
use ZipStream\ZipStream as BaseZipStream;
use ZipStream\Option\File as FileOptions;
use ZipStream\Option\Archive as ArchiveOptions;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /** @var Collection */
    protected $meta;

    /** @var bool */
    protected $calculateOnly = false;

    /** @var int */
    protected $predictedSize = 0;

    /**
     * @param ArchiveOptions $archiveOptions
     * @param FileOptions    $fileOptions
     */
    public function __construct( ArchiveOptions $archiveOptions, FileOptions $fileOptions )
    {
        parent::__construct(null, $archiveOptions);

        $this->archiveOptions = $archiveOptions;
        $this->fileOptions = $fileOptions;
        $this->queue = new Collection();
    }

    /**
     * @param string $name
     *
     * @param array  $files
     *
     * @return ZipStream
     */
    public function create( ?string $name = null, array $files = [] )
    {
        $zip = (new self($this->archiveOptions, $this->fileOptions))->setName($name);

        foreach ($files as $key => $value) {
            if (is_string($key)) {
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
    public function setName( string $name )
    {
        $this->output_name = $name;

        return $this;
    }

    /**
     * @param string|FileContract $source
     * @param string|null         $zipPath
     *
     * @return $this
     */
    public function add( $source, ?string $zipPath = null )
    {
        if (!$source instanceof FileContract) {
            $source = File::make($source, $zipPath);
        }

        // Don't add two files with the same zip path
        if (!$this->queue->has($source->getZipPath())) {
            $this->queue->put($source->getZipPath(), $source);
        }

        $this->predictedSize = 0;

        return $this;
    }

    /**
     * Explicitly add raw content instead of from file on disk
     *
     * @param        $content
     * @param string $zipPath
     *
     * @return $this
     */
    public function addRaw( $content, string $zipPath )
    {
        return $this->add(new TempFile($content, $zipPath));
    }

    /**
     * @param array $meta
     *
     * @return $this
     */
    public function setMeta( array $meta )
    {
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
        $this->bytesSent = 0;
        $this->configureZip64();

        event(new ZipStreaming($this));

        $predicted = $this->canPredictZipSize()
            ? $this->predictZipSize()
            : false;

        $this->queue->map->toZipStreamFile($this)->each->process();
        $this->finish();
        $this->getOutputStream()->close();

        if ($this->cacheOutputStream) {
            $this->cacheOutputStream->close();
        }

        event(new ZipStreamed($this));

        if ($predicted !== false && $predicted != $this->getFinalSize()) {
            event(new ZipSizePredictionFailed($this, $predicted, $this->getFinalSize()));
        }

        return $this->getFinalSize();
    }

    public function configureZip64()
    {
        $this->opt->setEnableZip64(
            // More than 65535 files
            count($this->files) > 0xFFFF
            // Filesize over max 32 bit integer
            || $this->queue->sum->getFilesize() > 0xFFFFFFFF
        );
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
    public function toResponse( $request )
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
            'Content-Length'            => $this->canPredictZipSize() ? $this->predictZipSize() : null
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
    public function cache( $output )
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
            $this->outputStream = Utils::streamFor($this->archiveOptions->getOutputStream());
        }

        return $this->outputStream;
    }

    /**
     * @param string|FileContract $output
     *
     * @return int
     * @throws OverflowException
     */
    public function saveTo( $output ): int
    {
        if (!$output instanceof FileContract) {
            $output = File::makeWriteable(Str::finish($output, "/") . $this->getName());
        }

        $this->outputStream = $output->getWritableStream();

        return $this->process();
    }

    /**
     * @param string $str
     */
    public function send( string $str ): void
    {
        $this->bytesSent += strlen($str);

        if ($this->calculateOnly) {
            return;
        }

        $this->getOutputStream()->write($str);

        if ($this->cacheOutputStream) {
            $this->cacheOutputStream->write($str);
        }
    }

    /**
     * @return bool
     */
    public function canPredictZipSize()
    {
        return $this->queue->every->canPredictZipDataSize()
            && config('zipstream.archive.predict');
    }

    /**
     * @return int
     */
    public function predictZipSize(): int
    {
        if (!$this->canPredictZipSize()) {
            return 0;
        }

        if ($this->predictedSize > 0) {
            return $this->predictedSize;
        }

        $this->configureZip64();
        $this->predictedSize = $this->calculateZipSize();

        // It's conceivable that we didn't need Zip64 until the zip headers/decriptors were added.
        // If so, turn on Zip64 and calculate again.
        if(!$this->opt->isEnableZip64() && $this->predictedSize > 0xFFFFFFFF) {
            $this->opt->setEnableZip64(true);
            $this->predictedSize = $this->calculateZipSize();
        }

        return $this->predictedSize;
    }

    /**
     * @return int
     */
    protected function calculateZipSize(): int
    {
        $this->bytesSent = 0;
        $this->calculateOnly = true;

        $this->queue->map->toZipStreamFile($this)->each->calculate();
        $this->finish();

        $this->calculateOnly = false;

        return $this->queue->sum->getFilesize() + $this->getFinalSize();
    }

    /**
     * @return string
     */
    public function getFingerprint(): string
    {
        return md5(
            $this->queue->map->getFingerprint()->sort()->implode('')
            . $this->getName()
            . serialize($this->getMeta()->sort()->toArray())
        );
    }

    /**
     *
     */
    protected function clear(): void
    {
        parent::clear();

        $this->opt = $this->archiveOptions;
    }
}
