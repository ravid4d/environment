<?php

namespace AmcLab\Tenancy;

use AmcLab\Tenancy\Contracts\Hook;
use AmcLab\Tenancy\Contracts\Resolver as Contract;
use AmcLab\Tenancy\Exceptions\ResolverException;
use AmcLab\Tenancy\Traits\HasConfigTrait;
use AmcLab\Tenancy\Traits\HasEventsDispatcherTrait;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

class Resolver implements Contract {

    use HasEventsDispatcherTrait;
    use HasConfigTrait;

    protected $app;
    protected $hooks;

    public function __construct(Application $app, Dispatcher $events) {
        $this->app = $app;
        $this->setEventsDispatcher($events);
    }

    public function boot(array $hooks = []) {
        // TODO: studiare se è possibile/sensato/opportuno spostare questo nel boot
        // (vedi problema iniezione automatica di ConnectionResolverInstance)

        if ($this->hooks) {
            throw new ResolverException('Resolver already booted');
        }

        $this->hooks = [];
        $list = $hooks ?: $this->config['hooks'];

        foreach ($list as $hook) {

            $with = [];

            if ($dependencies = $hook[1] ?? []) {
                foreach ($dependencies as $dependency) {
                    $with[] = $this->app->make($dependency);
                }
            }

            $this->hooks[] = $this->app->make($hook[0], $with);

        }

        return $this;
    }

    public function getHooks() : array {
        return $this->hooks;
    }

    public function get($entryName) {
        $entry = array_filter($this->hooks, function($v) use ($entryName){
            return $v->getEntry() === $entryName;
        }) ?? [];
        return array_pop($entry);
    }

    public function populate(array $package = [], array $params = []) : void {

        foreach ($this->hooks as &$hook) {

            $hook->purge();
            $entry = camel_case(substr(class_basename(get_class($hook)), 0, -4));

            if ($packageEntry = $package[$entry] ?? null){
                $hook->populate($packageEntry, $params);
            }

        }

    }

    public function purge() : void {

        foreach ($this->hooks as &$hook) {
            $hook->purge();
        }

    }

}
