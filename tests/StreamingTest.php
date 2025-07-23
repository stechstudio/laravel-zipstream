<?php

use Orchestra\Testbench\TestCase;
use STS\ZipStream\Facades\Zip;
use STS\ZipStream\ZipStreamServiceProvider;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamingTest extends TestCase
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

    protected function defineRoutes($router)
    {
        $router->get('/stream', function () {
            $testrun = microtime();
            file_put_contents("/tmp/test1.txt", "this is the first test file for test run $testrun");

            return Zip::create("test.zip")
                ->add("/tmp/test1.txt")
                ->then(fn() => unlink("/tmp/test1.txt"));
        });

        $router->get('/save', function () {
            $testrun = microtime();
            file_put_contents("/tmp/test1.txt", "this is the first test file for test run $testrun");

            $dir = "/tmp/" . Str::random();

            Zip::create("test.zip")
                ->add("/tmp/test1.txt")
                ->saveTo($dir);

            return "$dir/test.zip";
        });
    }

    public function testZipStream()
    {
        $response = $this->get('/stream');

        // All we really care about is that the response is a StreamedResponse
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
    }

    public function testZipSaveToDiskOnly()
    {
        $response = $this->get('/save');

        // Make sure we did NOT get a stream response this time, and that the zip exists
        $this->assertNotInstanceOf(StreamedResponse::class, $response->baseResponse);
        $this->assertTrue(file_exists($response->getContent()));

        unlink($response->getContent());
    }
}