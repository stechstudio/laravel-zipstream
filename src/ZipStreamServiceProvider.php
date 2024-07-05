<?php

namespace STS\ZipStream;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ZipStreamServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('zipstream')->hasConfigFile();

        $this->app->singleton('zipstream.builder', Builder::class);

        $this->app->singleton('zipstream.s3client', function($app) {
            $config = $app['config']->get('zipstream.aws');

            if(!count(array_filter($config['credentials']))) {
                unset($config['credentials']);
            }

            if($app['config']->get('zipstream.aws_anonymous_client')) {
                $config['credentials'] = false;
            }

            return new \Aws\S3\S3Client($config);
        });
    }

    /**
     * @return array
     */
    public function provides()
    {
        return [FileOptions::class, ArchiveOptions::class, 'zipstream', 'zipstream.s3client'];
    }

    /**
     * @return ArchiveOptions
     */
    protected function buildArchiveOptions(array $config)
    {
        return tap(new ArchiveOptions(), function(ArchiveOptions $options) use($config) {
            $options->setZeroHeader(true);
        });
    }

    /**
     * @return FileOptions
     */
    protected function buildFileOptions(array $config)
    {
        return tap(new FileOptions(), function(FileOptions $options) use($config) {
            $options->setMethod(Method::{strtoupper($config['method'])}());

            if($config['deflate']) {
                $options->setDeflateLevel($config['deflate']);
            }
        });
    }
}
