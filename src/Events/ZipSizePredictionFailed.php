<?php

namespace STS\ZipStream\Events;

use STS\ZipStream\ZipStream;

class ZipSizePredictionFailed
{
    /** @var ZipStream */
    public $zip;

    /** @var int */
    public $expected;

    /** @var int */
    public $actual;

    /**
     * @param ZipStream $zip
     * @param int $expected
     * @param int $actual
     */
    public function __construct(ZipStream $zip, int $expected, int $actual)
    {
        $this->zip = $zip;
        $this->expected = $expected;
        $this->actual = $actual;
    }
}