<?php

namespace STS\ZipStream\Tests;

use Illuminate\Support\Str;
use STS\ZipStream\Builder;
use ZipArchive;
use Orchestra\Testbench\TestCase;
use STS\ZipStream\Facades\Zip;
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
            'Zip' => Zip::class
        ];
    }

    public function testSaveZipOutput()
    {
        $testrun = microtime();
        file_put_contents("/tmp/test1.txt", "this is the first test file for test run $testrun");
        file_put_contents("/tmp/test2.txt", "this is the second test file for test run $testrun");

        /** @var Builder $zip */
        $zip = Zip::create("small.zip", ["/tmp/test1.txt", "/tmp/test2.txt"]);

        // Create a random folder path that doesn't exist, so we know it was created
        $dir = "/tmp/" . Str::random();
        $zip->saveTo($dir);

        $this->assertTrue(file_exists("$dir/small.zip"));

		$z = new ZipArchive();
        $z->open("$dir/small.zip");
        $this->assertEquals("this is the first test file for test run $testrun", $z->getFromIndex(0));

        unlink("$dir/small.zip");
    }

    /**
     * @group big
     */
    public function testSaveZip64Output()
    {
        $testrun = microtime();
        file_put_contents("/tmp/test1.txt", "this is the first test file for test run $testrun");
        file_put_contents("/tmp/test2.txt", "this is the second test file for test run $testrun");
        exec('dd if=/dev/zero count=5120 bs=1048576 >/tmp/bigfile.txt');
        exec('dd if=/dev/zero count=1024 bs=1048576 >/tmp/medfile.txt');

        /** @var Builder $zip */
        $zip = Zip::create("large.zip", ["/tmp/test1.txt", "/tmp/test2.txt", "/tmp/bigfile.txt", "/tmp/medfile.txt"]);
        $zip->saveTo("/tmp");

        $this->assertTrue(file_exists("/tmp/large.zip"));

        $z = new ZipArchive();
        $z->open("/tmp/large.zip");
        $this->assertEquals("this is the first test file for test run $testrun", $z->getFromIndex(0));

        unlink("/tmp/large.zip");
    }
}
