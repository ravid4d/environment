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

        $this->app->bind(Pathfinder::class, function($app) {
            return $app->make(\AmcLab\Tenancy\Pathfinder::class)
            ->setConfig($app['config']['tenancy.pathfinder']);
        });

        $this->app->bind(Resolver::class, function($app) {
            return $app->make(\AmcLab\Tenancy\Resolver::class)
            ->setHooks($this->registerHooks());
        });

        $this->app->bind(LockerService::class, function($app) {

            $config = $app['config']['tenancy.locker'];

            $this->app->bind(AuthorizedClientFactory::class, function($app) use ($config) {
                return $app->make(\AmcLab\Tenancy\Factories\AuthorizedClientFactory::class)
                ->create($config['auth']);
            });

            return $app->make(\AmcLab\Tenancy\Services\LockerService::class)
            ->setConfig($config)
            ->setClient($app->make(AuthorizedClientFactory::class));
        });

        $this->app->bind(ConciergeService::class, function($app) {
            return $app->make(\AmcLab\Tenancy\Services\ConciergeService::class)
            ->setConfig($app['config']['tenancy.concierge']);
        });


        $this->app->bind(Messenger::class, function($app) {
            $config = $app['config']['tenancy.messenger'];
            return $app->make(\AmcLab\Tenancy\Messenger::class)
            ->setCacheRepository($app['cache']->store($config['cache']['driver']))
            ->setConfig($config);
        });

        $this->app->bind(Tenant::class, function($app) {
            return $app->make(\AmcLab\Tenancy\Tenant::class)
            ->setConfig($app['config']['tenancy.tenant'])
            ->setConnectionResolver($app['db']);
        });

        $this->app->singleton(Tenancy::class, \AmcLab\Tenancy\Tenancy::class);
        $this->app->alias(Tenancy::class, 'tenancy');

    }

    protected function registerHooks() {

        // TODO: studiare se è possibile/sensato/opportuno spostare questo nel boot
        // (vedi problema iniezione automatica di ConnectionResolverInstance)

        $hooks = [];
        $list = $this->app['config']->get('tenancy.hooks');

        foreach ($list as $hook) {

            $with = [];

            if ($dependencies = $hook[1] ?? []) {
                foreach ($dependencies as $dependency) {
                    $with[] = $this->app->make($dependency);
                }
            }

            $hooks[] = $this->app->make($hook[0], $with);

        }

        return $hooks;

    }
}










/*
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

    public function boot()
    {
        $this->publishes(array(
            __DIR__.'../../config/tenancy.php' => config_path('tenancy.php'),
        ), 'config');
    }


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
*/
