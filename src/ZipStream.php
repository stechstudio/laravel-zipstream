<?php

namespace STS\ZipStream;

use function GuzzleHttp\Psr7\stream_for;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Psr\Http\Message\StreamInterface;
use STS\ZipStream\Models\File;
use ZipStream\Bigint;
use ZipStream\Exception\OverflowException;
use ZipStream\Option\Method;
use ZipStream\ZipStream as BaseZipStream;
use ZipStream\Option\Archive as ArchiveOptions;
use ZipStream\Option\File as FileOptions;
use Illuminate\Contracts\Support\Responsable;
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

    /** @var callable */
    protected $checkZipSize;

    /** @var StreamInterface */
    protected $outputStream;

    /** @var StreamInterface */
    protected $cacheOutputStream;

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
        return (new self($this->archiveOptions, $this->fileOptions))
            ->setName($name)
            ->add($files);
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
     * @param string|array|File $sources
     *
     * @return $this
     */
    public function add($sources)
    {
        foreach (Arr::wrap($sources) AS $source) {
            if (!$source instanceof File) {
                $source = File::make($source);
            }

            // Don't add two files with the same zip path
            if (!$this->queue->has($source->getZipPath())) {
                $this->queue->put($source->getZipPath(), $source);
            }
        }

        return $this;
    }

    /**
     * Builds the zip and writes to output stream
     *
     * @return int
     * @throws OverflowException
     */
    public function process(): int
    {
        $this->queue->each(function (File $file) {
            $this->addFileFromPsr7Stream($file->getZipPath(), $file->getReadableStream(), $this->fileOptions);
            $file->getReadableStream()->close();
        });

        $this->finish();
        $this->getOutputStream()->close();

        if($this->cacheOutputStream) {
            $this->cacheOutputStream->close();
        }

        if($this->checkZipSize && $this->canPredictZipSize()) {
            call_user_func($this->checkZipSize, $this->predictZipSize(), $this->getFinalSize(), $this);
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
            'Content-Length' => $this->canPredictZipSize() ? $this->predictZipSize() : null
        ]);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        // Various different browsers dislike various characters here. Strip them all for safety.
        $safe_output = trim(str_replace(['"', "'", '\\', ';', "\n", "\r"], '', $this->output_name ?? "download.zip"));

        // Check if we need to UTF-8 encode the filename
        return rawurlencode($safe_output);
    }

    /**
     * @return int
     */
    public function getFinalSize(): int
    {
        return $this->bytesSent;
    }

    /**
     * @param callable $callback
     *
     * @return $this
     */
    public function checkZipSize(callable $callback)
    {
        $this->checkZipSize = $callback;

        return $this;
    }

    /**
     * @param mixed $output
     *
     * @return ZipStream
     */
    public function cache($output)
    {
        if (!$output instanceof File) {
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
        if(!$this->outputStream) {
            $this->outputStream = stream_for($this->archiveOptions->getOutputStream());
        }

        return $this->outputStream;
    }

    /**
     * @param $output
     *
     * @return int
     * @throws OverflowException
     */
    public function saveTo($output): int
    {
        if (!$output instanceof File) {
            $output = File::make($output);
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

        if($this->cacheOutputStream) {
            $this->cacheOutputStream->write($str);
        }

        $this->bytesSent += strlen($str);
    }

    /**
     * @return bool
     */
    public function canPredictZipSize()
    {
        return $this->fileOptions->getMethod() == Method::STORE() && !$this->getTotalFilesizes()->isOver32();
    }

    /**
     * Stack Overflow FTW! http://stackoverflow.com/a/19380600/660694
     *
     * @return int
     */
    public function predictZipSize(): int
    {
        if (!$this->canPredictZipSize()) {
            throw new \RuntimeException("We can only determine a zip filesize in advance if compression is turned off and filesize is a 32-bit integer");
        }

        return $this->queue->count() * (30 + 46) + (2 * $this->getFilePathLengths()) + $this->getTotalFilesizes()->getValue() + 22;
    }

    /**
     * @return Bigint
     */
    public function getTotalFilesizes(): Bigint
    {
        return $this->queue->reduce(function (Bigint $bigInt, File $file) {
            return $bigInt->add(Bigint::init($file->getFilesize()));
        }, new Bigint());
    }

    /**
     * @return int
     */
    public function getFilePathLengths(): int
    {
        return $this->queue->sum(function (File $file) {
            return strlen($file->getZipPath());
        });
    }
}
