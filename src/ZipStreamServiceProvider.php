<?php

namespace STS\ZipStream;

use Illuminate\Support\ServiceProvider;
use ZipStream\Option\Archive;
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
        /*
         * Optional methods to load your package assets
         */
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'laravel-zipstream');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-zipstream');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('zipstream.php'),
            ], 'config');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/zipstream'),
            ], 'views');*/

            // Publishing assets.
            /*$this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/zipstream'),
            ], 'assets');*/

            // Publishing the translation files.
            /*$this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/zipstream'),
            ], 'lang');*/

            // Registering package commands.
            // $this->commands([]);
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
        return tap(new ArchiveOptions(), function(ArchiveOptions $options) {

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
