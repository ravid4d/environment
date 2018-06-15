<?php
namespace AmcLab\Tenancy\Providers;

use AmcLab\Tenancy\Contracts\Factories\AuthorizedClientFactory;
use AmcLab\Tenancy\Contracts\Messenger;
use AmcLab\Tenancy\Contracts\Pathfinder;
use AmcLab\Tenancy\Contracts\Resolver;
use AmcLab\Tenancy\Contracts\Services\ConciergeService;
use AmcLab\Tenancy\Contracts\Services\LockerService;
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

        $this->app->bind(AuthorizedClientFactory::class, function($app) use ($config) {
            return $app->make(\AmcLab\Tenancy\Factories\AuthorizedClientFactory::class)
            ->create($config['messenger']['locker']['client']);
            ;
        });

        $this->app->bind(Pathfinder::class, function($app) use ($config) {
            return $app->make(\AmcLab\Tenancy\Pathfinder::class)
            ->setConfig($config['pathfinder']);
        });

        $this->app->bind(LockerService::class, function($app) use ($config) {
            $client = $app->make(AuthorizedClientFactory::class);

            return $app->make(\AmcLab\Tenancy\Services\LockerService::class)
            ->setConfig($config['messenger']['locker'])
            ->setClient($client);
        });

        $this->app->bind(ConciergeService::class, function($app) use ($config) {
            return $app->make(\AmcLab\Tenancy\Services\ConciergeService::class)
            ->setConfig($config['messenger']['concierge']);
        });

        $this->app->bind(Resolver::class, function($app) use ($config) {
            return $app->make(\AmcLab\Tenancy\Resolver::class)
            ->setConfig($config['resolver'])
            ->boot();
        });

        $this->app->bind(Messenger::class, function($app) use ($config) {
            return $app->make(\AmcLab\Tenancy\Messenger::class)
            ->setConfig($config['messenger'])
            ->setCacheRepository($app['cache']->store($config['messenger']['cache']['driver']));
        });

        $this->app->bind(Tenant::class, function($app) use ($config) {
            return $app->make(\AmcLab\Tenancy\Tenant::class)
            ->setConfig($config['tenant'])
            ->setConnectionResolver($app['db']);
        });

        $this->app->singleton(Tenancy::class, function($app) {
            return $app->make(\AmcLab\Tenancy\Tenancy::class);
        });
        $this->app->alias(Tenancy::class, 'tenancy');

    }

}
