<?php

namespace STS\ZipStream;

use STS\ZipStream\Models\File;
use ZipStream\Bigint;
use ZipStream\ZipStream;
use Psr\Http\Message\StreamInterface;

/**
 *
 */
class ZipStreamFile extends \ZipStream\File
{
    /** @var File */
    protected $file;

    /**
     * @param ZipStream $zip
     * @param File $file
     */
    public function __construct(ZipStream $zip, File $file)
    {
        $this->file = $file;

        $opt = $file->getOptions();
        $opt->defaultTo($zip->opt);

        $this->zlen = new Bigint();
        $this->len = new Bigint();

        parent::__construct($zip, $file->getZipPath(), $opt);
    }

    /**
     * Processes a file stream and closes it right away
     */
    public function process()
    {
        tap($this->file->getReadableStream(), function (StreamInterface $stream) {
            $this->processStreamWithZeroHeader($stream);
            $stream->close();
        });
    }

    /**
     * This works similar to `processStreamWithZeroHeader` except it fakes sending the data
     * just so we can see what headers/descriptors are generated
     */
    public function calculate()
    {
        $this->bits |= self::BIT_ZERO_HEADER;

        $this->addFileHeader();

        $this->len = BigInt::init($this->file->getFilesize());
        $this->zlen = BigInt::init($this->file->getFilesize());

        $this->addFileFooter();
    }
}