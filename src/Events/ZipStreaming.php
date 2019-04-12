<?php

namespace STS\ZipStream\Events;

use STS\ZipStream\ZipStream;

class ZipStreaming
{
    /** @var ZipStream */
    public $zip;

    /**
     * @param ZipStream $zip
     */
    public function __construct(ZipStream $zip)
    {
        $this->zip = $zip;
    }
}