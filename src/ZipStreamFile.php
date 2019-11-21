<?php

namespace STS\ZipStream;

use STS\ZipStream\Models\File;
use ZipStream\Bigint;
use ZipStream\ZipStream;

/**
 *
 */
class ZipStreamFile extends \ZipStream\File
{
    /** @var File */
    protected $file;

    /**
     * ZipStreamFile constructor.
     *
     * @param ZipStream $zip
     * @param File      $file
     */
    public function __construct( ZipStream $zip, File $file )
    {
        $this->file = $file;

        $opt = $file->getOptions();
        $opt->defaultTo($zip->opt);

        $this->zlen = new Bigint();
        $this->len = new Bigint();

        parent::__construct($zip, $file->getZipPath(), $opt);
    }

    /**
     *
     */
    public function process( )
    {
        $this->processStreamWithZeroHeader($this->file->getReadableStream());
        $this->file->getReadableStream()->close();
    }

    /**
     * @throws \ZipStream\Exception\EncodingException
     */
    public function calculate()
    {
        $this->bits |= self::BIT_ZERO_HEADER;
        $this->addFileHeader();

        $this->len = BigInt::init($this->file->getFilesize());
        $this->zlen = Bigint::init($this->file->getFilesize());

        $this->addFileFooter();
    }
}