<?php

namespace STS\ZipStream\Tests;

use GuzzleHttp\Psr7\BufferStream;
use Illuminate\Support\Str;
use STS\ZipStream\Builder;
use STS\ZipStream\Models\File;
use ZipArchive;
use Orchestra\Testbench\TestCase;
use STS\ZipStream\Facades\Zip;
use STS\ZipStream\ZipStreamServiceProvider;

class ConflictTest extends TestCase
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

    public function testSkipConflictStrategy()
    {
        $testrun = microtime();
        file_put_contents("/tmp/test1", "this is the first test file for test run $testrun");
        file_put_contents("/tmp/test2", "this is the second test file for test run $testrun");

        config(['zipstream.conflict_strategy' => 'skip']);

        /** @var Builder $zip */
        $zip = Zip::create("test.zip");
        $zip->add("/tmp/test1", "subdir/test1.txt");
        $zip->add("/tmp/test2", "subdir/test1.txt");

        // Create a random folder path that doesn't exist, so we know it was created
        $dir = "/tmp/" . Str::random();
        $zip->saveTo($dir);

        $this->assertTrue(file_exists("$dir/test.zip"));

		$z = new ZipArchive();
        $z->open("$dir/test.zip");
        $this->assertEquals(1, $z->count());
        $this->assertEquals("this is the first test file for test run $testrun", $z->getFromName("subdir/test1.txt"));

        unlink("$dir/test.zip");
    }

    public function testReplaceConflictStrategy()
    {
        $testrun = microtime();
        file_put_contents("/tmp/test1", "this is the first test file for test run $testrun");
        file_put_contents("/tmp/test2", "this is the second test file for test run $testrun");

        config(['zipstream.conflict_strategy' => 'replace']);

        /** @var Builder $zip */
        $zip = Zip::create("test.zip");
        $zip->add("/tmp/test1", "subdir/test1.txt");
        $zip->add("/tmp/test2", "subdir/test1.txt");

        // Create a random folder path that doesn't exist, so we know it was created
        $dir = "/tmp/" . Str::random();
        $zip->saveTo($dir);

        $this->assertTrue(file_exists("$dir/test.zip"));

		$z = new ZipArchive();
        $z->open("$dir/test.zip");
        $this->assertEquals(1, $z->count());
        $this->assertEquals("this is the second test file for test run $testrun", $z->getFromName("subdir/test1.txt"));

        unlink("$dir/test.zip");
    }

    public function testRenameConflictStrategy()
    {
        $testrun = microtime();
        file_put_contents("/tmp/test1", "this is the first test file for test run $testrun");
        file_put_contents("/tmp/test2", "this is the second test file for test run $testrun");

        config(['zipstream.conflict_strategy' => 'rename']);

        /** @var Builder $zip */
        $zip = Zip::create("test.zip");
        $zip->add("/tmp/test1", "subdir/test1.txt");
        $zip->add("/tmp/test2", "subdir/test1.txt");
        $zip->add("/tmp/test1", "filename");
        $zip->add("/tmp/test2", "filename");

        // Create a random folder path that doesn't exist, so we know it was created
        $dir = "/tmp/" . Str::random();
        $zip->saveTo($dir);

        $this->assertTrue(file_exists("$dir/test.zip"));

		$z = new ZipArchive();
        $z->open("$dir/test.zip");
        $this->assertEquals(4, $z->count());
        $this->assertEquals('subdir/test1.txt', $z->getNameIndex(0));
        $this->assertEquals('subdir/test1_1.txt', $z->getNameIndex(1));
        $this->assertEquals('filename', $z->getNameIndex(2));
        $this->assertEquals('filename_1', $z->getNameIndex(3));
        $this->assertEquals("this is the first test file for test run $testrun", $z->getFromName("subdir/test1.txt"));
        $this->assertEquals("this is the second test file for test run $testrun", $z->getFromName("subdir/test1_1.txt"));
        $this->assertEquals("this is the first test file for test run $testrun", $z->getFromName("filename"));
        $this->assertEquals("this is the second test file for test run $testrun", $z->getFromName("filename_1"));

        unlink("$dir/test.zip");
    }

    public function testCaseInsensitictConflictHandling()
    {
        $testrun = microtime();
        file_put_contents("/tmp/test1", "this is the first test file for test run $testrun");
        file_put_contents("/tmp/test2", "this is the second test file for test run $testrun");
        file_put_contents("/tmp/test3", "this is the third test file for test run $testrun");

        config(['zipstream.conflict_strategy' => 'rename', 'zipstream.case_insensitive_conflicts' => true]);

        /** @var Builder $zip */
        $zip = Zip::create("test.zip");
        $zip->add("/tmp/test1", "subdir/test1.txt");
        $zip->add("/tmp/test2", "subdir/test1.txt");
        $zip->add("/tmp/test3", "subdir/TEST1.TXT");

        // Create a random folder path that doesn't exist, so we know it was created
        $dir = "/tmp/" . Str::random();
        $zip->saveTo($dir);

        $this->assertTrue(file_exists("$dir/test.zip"));

		$z = new ZipArchive();
        $z->open("$dir/test.zip");
        $this->assertEquals(3, $z->count());
        $this->assertEquals('subdir/test1_1.txt', $z->getNameIndex(1));
        $this->assertEquals('subdir/TEST1_2.TXT', $z->getNameIndex(2));
        $this->assertEquals("this is the first test file for test run $testrun", $z->getFromName("subdir/test1.txt"));
        $this->assertEquals("this is the second test file for test run $testrun", $z->getFromName("subdir/test1_1.txt"));
        $this->assertEquals("this is the third test file for test run $testrun", $z->getFromName("subdir/TEST1_2.TXT"));

        unlink("$dir/test.zip");
    }

    public function testCaseSensitiveConflictHandling()
    {
        $testrun = microtime();
        file_put_contents("/tmp/test1", "this is the first test file for test run $testrun");
        file_put_contents("/tmp/test2", "this is the second test file for test run $testrun");
        file_put_contents("/tmp/test3", "this is the third test file for test run $testrun");

        config(['zipstream.conflict_strategy' => 'rename', 'zipstream.case_insensitive_conflicts' => false]);

        /** @var Builder $zip */
        $zip = Zip::create("test.zip");
        $zip->add("/tmp/test1", "subdir/test1.txt");
        $zip->add("/tmp/test2", "subdir/test1.txt");
        $zip->add("/tmp/test3", "subdir/TEST1.TXT");

        // Create a random folder path that doesn't exist, so we know it was created
        $dir = "/tmp/" . Str::random();
        $zip->saveTo($dir);

        $this->assertTrue(file_exists("$dir/test.zip"));

		$z = new ZipArchive();
        $z->open("$dir/test.zip");
        $this->assertEquals(3, $z->count());
        $this->assertEquals('subdir/test1_1.txt', $z->getNameIndex(1));
        $this->assertEquals('subdir/TEST1.TXT', $z->getNameIndex(2));
        $this->assertEquals("this is the first test file for test run $testrun", $z->getFromName("subdir/test1.txt"));
        $this->assertEquals("this is the second test file for test run $testrun", $z->getFromName("subdir/test1_1.txt"));
        $this->assertEquals("this is the third test file for test run $testrun", $z->getFromName("subdir/TEST1.TXT"));

        unlink("$dir/test.zip");
    }
}
