<?php

namespace STS\ZipStream\Tests\SizeEstimation;

use STS\ZipStream\Contracts\FileContract;

abstract class SingleFileArchiveSizeEstimationTestCase extends EmptyArchiveSizeEstimationTest
{
    /** @var string */
    public $zipPath = 'file.txt';

    public function setUp(): void
    {
        parent::setUp();

        $this->zip->add($this->getFile($this->zipPath));
    }

    abstract protected function getFile(string $zipPath): FileContract;
}
