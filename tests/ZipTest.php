<?php

namespace STS\ZipStream\Tests;

use STS\ZipStream\ZipStream;
use Zip;
use Orchestra\Testbench\TestCase;
use STS\ZipStream\ZipStreamFacade;
use STS\ZipStream\ZipStreamServiceProvider;

class ZipTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ZipStreamServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Zip' => ZipStreamFacade::class
        ];
    }

    public function testSaveZipOutput()
    {
        file_put_contents("/tmp/test1.txt", "this is the first test file for test run: " . microtime());
        file_put_contents("/tmp/test2.txt", "this is the second test file for test run:" . microtime());

        /** @var ZipStream $zip */
        $zip = Zip::create("my.zip", ["/tmp/test1.txt", "/tmp/test2.txt"]);
        $zip->saveTo("/tmp");

        $this->assertTrue(file_exists("/tmp/my.zip"));
        $this->assertEquals($zip->predictedZipSize(), filesize("/tmp/my.zip"));
    }
}