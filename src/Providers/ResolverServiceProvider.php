<?php

namespace AmcLab\Tenancy\Providers;

use AmcLab\Tenancy\Contracts\Resolver as ResolverContract;
use AmcLab\Tenancy\Resolver;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

class ResolverServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */

    public function register()
    {

        $this->registerHooks();

        $this->app->bind(ResolverContract::class, function($app) {

            $config = $app['config']->get('tenancy.hooks');
            return (new Resolver($config, ...$app->tagged('tenancy.hooks')))
            ->setEventDispatcher($app[Dispatcher::class]);

        });

    }

    protected function registerHooks() {

        $list = $this->app['config']->get('tenancy.hooks');

        foreach ($list as $specs) {

            $this->app->bind($specs['alias'], function($app) use ($specs) {

                if (isset($specs['needs'])) {
                    foreach ($specs['needs'] as $need) {
                        $needed[] = $app->make($need);
                    }
                }
                else {
                    $needed = [];
                }

                return new $specs['implementation'](...$needed);

            });
        }

        $this->app->tag(array_column($list, 'alias'), 'tenancy.hooks');

    }

}
