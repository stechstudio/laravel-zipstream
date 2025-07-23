<?php

use GuzzleHttp\Psr7\BufferStream;
use Illuminate\Support\Str;
use STS\ZipStream\Builder;
use STS\ZipStream\Models\File;
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
        $zip = Zip::create("test.zip", ["/tmp/test1.txt", "/tmp/test2.txt"]);

        // Create a random folder path that doesn't exist, so we know it was created
        $dir = "/tmp/" . Str::random();
        $zip->saveTo($dir);

        $this->assertTrue(file_exists("$dir/test.zip"));

		$z = new ZipArchive();
        $z->open("$dir/test.zip");
        $this->assertEquals("this is the first test file for test run $testrun", $z->getFromIndex(0));

        unlink("$dir/test.zip");
    }

    public function testSaveZipOutputWithCaching()
    {
        $testrun = microtime();
        file_put_contents("/tmp/test1.txt", "this is the first test file for test run $testrun");
        file_put_contents("/tmp/test2.txt", "this is the second test file for test run $testrun");

        /** @var Builder $zip */
        $zip = Zip::create("test.zip", ["/tmp/test1.txt", "/tmp/test2.txt"]);

        // Create a random folder path that doesn't exist, so we know it was created
        $dir = "/tmp/" . Str::random();

        // This time we're going to CACHE the file to disk while setting the primary output elsewhere
        $zip->cache("$dir/test.zip");

        // We'll save it to a memory buffer as the primary output
        $size = $zip->saveTo($stream = new BufferStream());

        $this->assertTrue(file_exists("$dir/test.zip"));

        // We should now have a matching buffer size and cached zip file on disk
        $this->assertEquals($size, $stream->getSize());
        $this->assertEquals($size, filesize("$dir/test.zip"));

        unlink("$dir/test.zip");
    }

    public function testAfterProcessingCallback()
    {
        $testrun = microtime();
        file_put_contents("/tmp/test1.txt", "this is the first test file for test run $testrun");
        file_put_contents("/tmp/test2.txt", "this is the second test file for test run $testrun");

        /** @var Builder $zip */
        $zip = Zip::create("test.zip", ["/tmp/test1.txt", "/tmp/test2.txt"]);
        $result = null;
        $zip->then(function($builder, $zip, $size) use(&$result) {
            $result = "Zip finished streaming with a total of $size bytes";
        });

        $this->assertNull($result);

        $dir = "/tmp/" . Str::random();
        $zip->saveTo($dir);

        $this->assertEquals("Zip finished streaming with a total of " . filesize("$dir/test.zip") . " bytes", $result);
        unlink("$dir/test.zip");
    }

    public function testZipHas()
    {
        $testrun = microtime();
        file_put_contents("/tmp/test1.txt", "this is the first test file for test run $testrun");
        file_put_contents("/tmp/test2.txt", "this is the second test file for test run $testrun");

        /** @var Builder $zip */
        $zip = Zip::create("test.zip", [
            "/tmp/test1.txt",
            "/tmp/test2.txt" => "/subfolder/test2.txt"
        ]);

        $this->assertTrue($zip->has("test1.txt"));
        $this->assertTrue($zip->has("/test1.txt"));
        $this->assertFalse($zip->has("test2.txt"));
        $this->assertTrue($zip->has("subfolder/test2.txt"));
        $this->assertTrue($zip->has("/subfolder/test2.txt"));
    }

    public function testSaveToDisk()
    {
        config([
            'filesystems.disks.tmp' => [
                'driver' => 'local',
                'root' => '/tmp',
                'prefix' => 'my-prefix',
            ],
        ]);

        $testrun = microtime();
        file_put_contents("/tmp/test1.txt", "this is the first test file for test run $testrun");
        file_put_contents("/tmp/test2.txt", "this is the second test file for test run $testrun");

        /** @var Builder $zip */
        $zip = Zip::create("test.zip", ["/tmp/test1.txt", "/tmp/test2.txt"]);

        $folder = Str::random();
        $zip->saveToDisk('tmp', $folder);

        $this->assertTrue(file_exists("/tmp/my-prefix/$folder/test.zip"));
        unlink("/tmp/my-prefix/$folder/test.zip");
    }
}
