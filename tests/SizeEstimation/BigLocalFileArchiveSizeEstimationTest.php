<?php

namespace STS\ZipStream\Tests\SizeEstimation;

use STS\ZipStream\Contracts\FileContract;
use STS\ZipStream\Models\LocalFile;

class BigLocalFileArchiveSizeEstimationTest extends SingleFileArchiveSizeEstimationTestCase
{
    private const TEST_FILE_PATH = "/tmp/bigfile.txt";

    public static function setUpBeforeClass(): void
    {
        exec('dd if=/dev/zero count=5120 bs=1048576 >/tmp/bigfile.txt');
    }

    public static function tearDownAfterClass(): void
    {
        if (file_exists(self::TEST_FILE_PATH)) {
            unlink(self::TEST_FILE_PATH);
        }
    }

    protected function getFile(string $zipPath): FileContract
    {
        return new LocalFile(self::TEST_FILE_PATH, $zipPath);
    }
}
