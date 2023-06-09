<?php

namespace AmcLab\Environment;

use AmcLab\Baseline\Traits\HasEventsDispatcherTrait;
use AmcLab\Environment\Contracts\Hook;
use AmcLab\Environment\Contracts\Resolver as Contract;
use AmcLab\Environment\Exceptions\ResolverException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;

class Resolver implements Contract {

    use HasEventsDispatcherTrait;

    protected $app;
    protected $config;
    protected $hooks;

    public function __construct(Application $app, Repository $configRepository, Dispatcher $events) {
        $this->app = $app;
        $this->config = $configRepository->get('environment.resolver');
        $this->setEventsDispatcher($events);
    }

    public function bootstrap(array $hooks = []) {

        if ($this->hooks) {
            throw new ResolverException('Resolver already bootstrapped', 1001);
        }

        $this->hooks = [];
        $list = $hooks ?: array_keys($this->config['hooks']);

        foreach ($list as $hook) {
            $this->hooks[] = $this->app->make($hook);
        }

        return $this;
    }

    public function getHooks() : array {
        return $this->hooks;
    }

    public function get($entryName) {

        if (!$this->hooks) {
            throw new ResolverException('Resolver needs to be bootstrapped', 1000);
        }

        $entry = array_filter($this->hooks, function($v) use ($entryName){
            return $v->getEntry() === $entryName;
        }) ?? [];

        return array_pop($entry);
    }

    public function use($entryName) {
        return $this->get($entryName)->use();
    }

    public function generate($entryName) {
        return $this->get($entryName)->generate();
    }

    public function populate(array $package = [], array $params = []) : void {

        if (!$this->hooks) {
            throw new ResolverException('Resolver needs to be bootstrapped', 1000);
        }

        foreach ($this->hooks as &$hook) {

            $hook->purge();
            $entry = Str::camel(substr(class_basename(get_class($hook)), 0, -4));

            if ($packageEntry = $package[$entry] ?? null){
                $hook->populate($packageEntry, $params);
            }

        }

    }

    public function purge() : void {

        if (!$this->hooks) {
            throw new ResolverException('Resolver needs to be bootstrapped', 1000);
        }

        foreach ($this->hooks as &$hook) {
            $hook->purge();
        }

    }

}
