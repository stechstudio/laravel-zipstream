<?php

namespace STS\ZipStream;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use STS\ZipStream\Models\ZipFile;
use ZipStream\Bigint;
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
     * @return ZipStream
     */
    public function create(string $name)
    {
        return (new self($this->opt, $this->fileOptions))->setName($name);
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
     * @param ZipFile $file
     *
     * @return $this
     */
    public function add(ZipFile $file)
    {

        if (!$this->queue->has($file->getFingerprint())) {
            $this->queue->put($file->getFingerprint(), $file);
        }

        return $this;
    }

    /**
     * Builds the zip and writes to output stream
     *
     * @return int
     * @throws \ZipStream\Exception\OverflowException
     */
    public function process(): int
    {
        //dd($this->getHeaders());
        $this->queue->each(function (ZipFile $file) {
            $this->addFileFromPsr7Stream($file->getZipPath(), $file->getHandle(), $this->fileOptions);
        });

        $this->finish();

        return $this->getFinalSize();
    }

    /**
     * @param Request $request
     *
     * @return StreamedResponse
     */
    public function toResponse($request)
    {
        //dd($this->getHeaders());
        return new StreamedResponse(function () {
            $this->process();
        }, 200, $this->getHeaders());
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
            //'Content-Length' => $this->canDetermineZipSize() ? $this->determineZipSize() : null
        ]);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        // Various different browsers dislike various characters here. Strip them all for safety.
        $safe_output = trim(str_replace(['"', "'", '\\', ';', "\n", "\r"], '', $this->output_name));

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
     * Keep track of how much data we've sent
     *
     * @param string $str
     */
    public function send(string $str): void
    {
        parent::send($str);

        $this->bytesSent += strlen($str);
    }

    /**
     * @return bool
     */
    public function canDetermineZipSize()
    {
        return $this->fileOptions->getMethod() == Method::STORE() && !$this->getTotalFilesizes()->isOver32();
    }

    /**
     * Stack Overflow FTW! http://stackoverflow.com/a/19380600/660694
     *
     * @return int
     */
    public function determineZipSize(): int
    {
        if (!$this->canDetermineZipSize()) {
            throw new \RuntimeException("We can only determine a zip filesize in advance if compression is turned off and filesize is a 32-bit integer");
        }

        return $this->queue->count() * (30 + 46) + (2 * $this->getFilePathLengths()) + $this->getTotalFilesizes()->getValue() + 22;
    }

    /**
     * @return Bigint
     */
    public function getTotalFilesizes(): Bigint
    {
        return $this->queue->reduce(function (Bigint $bigInt, ZipFile $file) {
            return $bigInt->add(Bigint::init($file->getFilesize()));
        }, new Bigint());
    }

    /**
     * @return int
     */
    public function getFilePathLengths(): int
    {
        return $this->queue->sum(function (ZipFile $file) {
            return strlen($file->getZipPath());
        });
    }
}
