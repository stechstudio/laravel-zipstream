<?php

namespace STS\ZipStream\Tests\SizeEstimation;

use STS\ZipStream\Contracts\FileContract;
use STS\ZipStream\Models\LocalFile;

class SingleLocalFileArchiveSizeEstimationTest extends SingleFileArchiveSizeEstimationTestCase
{
    private const TEST_FILE_PATH = "/tmp/file.txt";

    public static function setUpBeforeClass(): void
    {
        file_put_contents(self::TEST_FILE_PATH, "hi there");
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
