<?php


namespace LucianoJr\LaravelApiQueryHandler\Providers;


use Illuminate\Support\ServiceProvider;

class LaravelApiQueryHandlerServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__ . '/../config/config.php';

        $this->publishes([
            $configPath => config_path('luciano-jr/laravel-api-query-handler.php'),
        ]);
    }
}
