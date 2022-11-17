<?php

namespace STS\ZipStream;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use ZipStream\Option\Archive as ArchiveOptions;
use ZipStream\Option\File as FileOptions;
use ZipStream\Option\Method;

class ZipStreamServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('zipstream.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'zipstream');

        // Register the main class to use with the container binding
        $this->app->singleton('zipstream', ZipStream::class);

        $this->app->bind(FileOptions::class, function($app) {
            return $this->buildFileOptions($app['config']->get('zipstream.file'));
        });

        $this->app->bind(ArchiveOptions::class, function($app) {
            return $this->buildArchiveOptions($app['config']->get('zipstream.archive'));
        });

        $this->app->bind('zipstream.s3client', function($app) {
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
