<?php
namespace AmcLab\Environment\Providers;

use AmcLab\Baseline\Contracts\PackageStore;
use AmcLab\Environment\Contracts\Environment;
use AmcLab\Environment\Contracts\MigrationManager;
use AmcLab\Environment\Contracts\Resolver;
use AmcLab\Environment\Contracts\Scope;
use AmcLab\Environment\Contracts\Store;
use AmcLab\Environment\Contracts\Tenant;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

class EnvironmentServiceProvider extends ServiceProvider
{

    public function boot(Environment $environment)
    {
        $this->publishes(array(
            __DIR__.'../../config/environment.php' => config_path('environment.php'),
        ), 'config');

        $environment->boot();

    }

    public function register()
    {
        $config = $this->app['config']['environment'];

        $this->app->bind(MigrationManager::class, \AmcLab\Environment\MigrationManager::class);
        $this->app->bind(PackageStore::class, $config['package-store']);

        $this->app->bind(Resolver::class, \AmcLab\Environment\Resolver::class);
        $this->app->bind(Tenant::class, \AmcLab\Environment\Tenant::class);
        $this->app->bind(Scope::class, \AmcLab\Environment\Scopes\DefaultScope::class);

        $this->app->singleton(Environment::class, \AmcLab\Environment\Environment::class);
        $this->app->alias(Environment::class, 'environment');

    }

}
