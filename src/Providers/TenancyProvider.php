<?php
namespace AmcLab\Tenancy\Providers;

use AmcLab\Baseline\Contracts\PackageStore;
use AmcLab\Tenancy\Contracts\MigrationManager;
use AmcLab\Tenancy\Contracts\Resolver;
use AmcLab\Tenancy\Contracts\Store;
use AmcLab\Tenancy\Contracts\Tenancy;
use AmcLab\Tenancy\Contracts\Tenant;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

class TenancyProvider extends ServiceProvider
{

    public function boot(Tenancy $tenancy)
    {
        $this->publishes(array(
            __DIR__.'../../config/tenancy.php' => config_path('tenancy.php'),
        ), 'config');

        $tenancy->getTenant()->setConnectionResolver($this->app['db']);

        $tenancy->getTenant()->getResolver()->bootstrap();

    }

    public function register()
    {
        $config = $this->app['config']['tenancy'];

        $this->app->bind(MigrationManager::class, \AmcLab\Tenancy\MigrationManager::class);
        $this->app->bind(PackageStore::class, $config['package-store']);

        $this->app->bind(Resolver::class, \AmcLab\Tenancy\Resolver::class);
        $this->app->bind(Tenant::class, \AmcLab\Tenancy\Tenant::class);
        $this->app->singleton(Tenancy::class, \AmcLab\Tenancy\Tenancy::class);
        $this->app->alias(Tenancy::class, 'tenancy');

    }

}
