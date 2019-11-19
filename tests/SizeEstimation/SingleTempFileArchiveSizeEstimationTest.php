<?php

namespace STS\ZipStream\Tests\SizeEstimation;

use STS\ZipStream\Contracts\FileContract;
use STS\ZipStream\Models\TempFile;

class SingleTempFileArchiveSizeEstimationTest extends SingleFileArchiveSizeEstimationTestCase
{
    protected function getFile(string $zipPath): FileContract
    {
        return new TempFile("hi there", $zipPath);
    }
}
