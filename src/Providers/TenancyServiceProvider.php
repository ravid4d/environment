<?php

namespace AmcLab\Tenancy\Providers;

use AmcLab\Tenancy\Contracts\Environment as EnvironmentContract;
use AmcLab\Tenancy\Contracts\Factories\AuthorizedClientFactory as AuthorizedClientFactoryContract;
use AmcLab\Tenancy\Contracts\Messenger as MessengerContract;
use AmcLab\Tenancy\Contracts\Pathfinder as PathfinderContract;
use AmcLab\Tenancy\Contracts\Resolver as ResolverContract;
use AmcLab\Tenancy\Contracts\Services\ConciergeService;
use AmcLab\Tenancy\Contracts\Services\LockerService;
use AmcLab\Tenancy\Contracts\Tenancy as TenancyContract;
use AmcLab\Tenancy\Contracts\Tenant;
use AmcLab\Tenancy\Environment;
use AmcLab\Tenancy\Factories\AuthorizedClientFactory;
use AmcLab\Tenancy\Messenger;
use AmcLab\Tenancy\Pathfinder;
use AmcLab\Tenancy\Resolver;
use AmcLab\Tenancy\Tenancy;
use App\Facades\TenancyFacade;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes(array(
            __DIR__.'../../config/tenancy.php' => config_path('tenancy.php'),
        ), 'config');
    }

    /**
     * Register the application services.
     *
     * @return void
     */

    public function register()
    {

        $this->registerAuthorizedClient();
        $this->registerPathfinder();
        $this->registerServices();

        $this->app->register(MessengerServiceProvider::class);
        $this->app->register(ResolverServiceProvider::class);

        $this->app->singleton(TenancyContract::class, function($app) {
            return (new Tenancy($app->make(Tenant::class), $app))
            ->setEventDispatcher($app[Dispatcher::class]);
        });

        $this->app->alias(TenancyContract::class, 'tenancy');

    }

    protected function registerAuthorizedClient() {
        $config = $this->app['config']['tenancy'];
        $this->app->bind(AuthorizedClientFactoryContract::class, function ($app) use ($config) {
            return (new AuthorizedClientFactory())->create($config['auth']);
        });
    }

    protected function registerPathfinder() {
        $config = $this->app['config']['tenancy'];
        $this->app->bind(PathfinderContract::class, function ($app) use ($config) {
            return new Pathfinder($config['pathfinder']);
        });
    }

    protected function registerServices() {
        $config = $this->app['config']['tenancy']['services'];

        $this->app->bind(Tenant::class, function($app) use ($config) {
            return (new \AmcLab\Tenancy\Tenant(
                $config['tenant'] ?? [],
                $app[MessengerContract::class],
                $app[ResolverContract::class],
                $app[PathfinderContract::class],
                $app[Kernel::class],
                $app['db']
            ))->setEventDispatcher($app[Dispatcher::class]);
        });

        $this->app->bind(LockerService::class, function($app) use ($config) {
            return new \AmcLab\Tenancy\Services\LockerService($config['locker'] ?? [], $app[AuthorizedClientFactoryContract::class]);
        });

        $this->app->bind(ConciergeService::class, function($app) use ($config) {
            return new \AmcLab\Tenancy\Services\ConciergeService($config['concierge'] ?? []);
        });
    }

}
