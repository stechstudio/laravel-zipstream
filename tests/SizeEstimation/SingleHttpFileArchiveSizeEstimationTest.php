<?php

namespace STS\ZipStream\Tests\SizeEstimation;

use STS\ZipStream\Contracts\FileContract;
use STS\ZipStream\Models\HttpFile;

class SingleHttpFileArchiveSizeEstimationTest extends SingleFileArchiveSizeEstimationTestCase
{
    private const TEST_URL = "https://raw.githubusercontent.com/stechstudio/laravel-zipstream/master/README.md";

    protected function getFile(string $zipPath): FileContract
    {
        return new HttpFile(self::TEST_URL, $zipPath);
    }

    public function provideSupportedZeroHeaderOptions()
    {
        // Since the HttpFile does not support seeking
        // The only supported zero header option is true.
        return [
            [
                true,
            ],
        ];
    }
}
