<?php
namespace AmcLab\Tenancy\Providers;

use AmcLab\Tenancy\Contracts\Resolver;
use AmcLab\Tenancy\Contracts\Store;
use AmcLab\Tenancy\Contracts\Tenancy;
use AmcLab\Tenancy\Contracts\Tenant;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $this->publishes(array(
            __DIR__.'../../config/tenancy.php' => config_path('tenancy.php'),
        ), 'config');
    }

    public function register()
    {
        $config = $this->app['config']['tenancy'];

        $this->app->bind(Store::class, $this->app['config']['tenancy.store']);
        $this->app->bind(Resolver::class, function($app) use ($config) {
            return $app->make(\AmcLab\Tenancy\Resolver::class)
            ->setConfig($config['resolver'])
            ->boot();
        });

        $this->app->bind(AuthorizedClientFactory::class, function($app) use ($config) {
            return $app->make(\AmcLab\Tenancy\Factories\AuthorizedClientFactory::class)
            ->create($config['messenger']['locker']['client']);
            ;
        });

        $this->app->bind(Tenant::class, function($app) use ($config) {
            return $app->make(\AmcLab\Tenancy\Tenant::class, ['store' => $app->make(Store::class)])
            ->setConfig($config['tenant'])
            ->setConnectionResolver($app['db']);
        });

        $this->app->singleton(Tenancy::class, function($app) {
            return $app->make(\AmcLab\Tenancy\Tenancy::class);
        });
        $this->app->alias(Tenancy::class, 'tenancy');

    }

}
