<?php

namespace STS\ZipStream;

use Illuminate\Support\ServiceProvider;
use ZipStream\Option\Archive as ArchiveOptions;
use ZipStream\Option\File as FileOptions;
use ZipStream\Option\Method;

class ZipStreamServiceProvider extends ServiceProvider
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
    }

    /**
     * @return array
     */
    public function provides()
    {
        return [FileOptions::class, ArchiveOptions::class, 'zipstream'];
    }

    /**
     * @return ArchiveOptions
     */
    protected function buildArchiveOptions(array $config)
    {
        return tap(new ArchiveOptions(), function(ArchiveOptions $options) use($config) {
            $options->setEnableZip64($config['zip64']);
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
