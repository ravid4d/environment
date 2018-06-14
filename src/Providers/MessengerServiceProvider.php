<?php

namespace AmcLab\Tenancy\Providers;

use AmcLab\Tenancy\Contracts\Factories\AuthorizedClientFactory;
use AmcLab\Tenancy\Contracts\Messenger as MessengerContract;
use AmcLab\Tenancy\Contracts\Pathfinder;
use AmcLab\Tenancy\Contracts\Services\ConciergeService;
use AmcLab\Tenancy\Contracts\Services\LockerService;
use AmcLab\Tenancy\Messenger;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

class MessengerServiceProvider extends ServiceProvider
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

        // registra l'istanza del messenger
        $this->app->bind(MessengerContract::class, function($app) {

            $cacheDriver = $app['config']['tenancy']['cache']['driver'];

            return (new Messenger($app['config']['tenancy'], $app['cache']->store($cacheDriver), $app['encrypter']))
            ->withConciergeService($app[ConciergeService::class])
            ->withLockerService($app[LockerService::class])
            ->withPathfinder($app[Pathfinder::class])
            ->setEventDispatcher($app[Dispatcher::class]);

        });

    }

}
