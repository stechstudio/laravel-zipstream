<?php

namespace STS\ZipStream\Tests\SizeEstimation;

use STS\ZipStream\ZipStreamOld;
use Zip;
use Orchestra\Testbench\TestCase;
use STS\ZipStream\Facades\Zip;
use STS\ZipStream\ZipStreamServiceProvider;

class SizeEstimationTestCase extends TestCase
{
    /** @var ZipStreamOld */
    protected $zip;
    /** @var string */
    protected $zipName = "zip.zip";

    public function setUp(): void
    {
        parent::setUp();

        $this->zip = $zip = Zip::create($this->zipName);
    }

    public function tearDown(): void
    {
        $filepath = "/tmp/" . $this->zip->getName();
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [ZipStreamServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Zip' => Zip::class
        ];
    }

    public function assertSizeEstimation()
    {
        $estimate = $this->zip->predictZipSize();

        $this->zip->saveTo("/tmp");

        $filepath = "/tmp/" . $this->zip->getName();

        $this->assertTrue(file_exists($filepath));
        $this->assertEquals(filesize($filepath), $estimate);

        unlink($filepath);
    }

    public function provideSupportedEnableZip64Options()
    {
        return [
            [
                true,
            ],
            [
                false,
            ],
        ];
    }

    public function provideSupportedZeroHeaderOptions()
    {
        return [
            [
                true,
            ],
            [
                false,
            ],
        ];
    }
}
