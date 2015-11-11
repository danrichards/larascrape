<?php

namespace Dan\Larascrape\Providers;

use Illuminate\Support\ServiceProvider;

class LarascrapeServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../../../config/larascrape.php' => config_path('larascrape.php'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bindShared('larascrape', function ($app) {
            return new Larascrape();
        });
    }
}
