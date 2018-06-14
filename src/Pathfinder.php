<?php

namespace AmcLab\Tenancy;

use AmcLab\Tenancy\Contracts\PathFinder as Contract;
use AmcLab\Tenancy\Exceptions\PathfinderException;

class Pathfinder implements Contract {

    protected $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function for(array $breadcrumbs = []) {

        if (!count($breadcrumbs)) {
            throw new PathfinderException('No path to follow');
        }

        if (is_array($breadcrumbs[0])) {
            $breadcrumbs = array_shift($breadcrumbs);
        }

        return [

            // originale
            'breadcrumbs' => $breadcrumbs,

            // costruisce la catena di pezzi per comporre il resourceId
            'resourceId' => $__resourceId = $this->mergeChain('resourceId', $breadcrumbs),

            // genera lo stack di chiavi per la cifratura
            'hashable' => $__hashable = $this->mergeChain('hashable', $__resourceId, true),

        ];

    }

    /**
     * Helper per unire un array dall'indice 'chains' con un secondo array
     *
     * @param string $name
     * @param array $breadcrumbs
     * @return void
     */
    protected function mergeChain(string $name, array $breadcrumbs, $encode = false) {

        $chain = $this->config['chains'][$name];
        if ($encode) {
            $chain = array_map(function($v){
                return md5($v);
            }, $chain);
        }
        return array_merge($chain, $breadcrumbs);

    }
}
