<?php

namespace AmcLab\Tenancy;

use AmcLab\Tenancy\Contracts\PathFinder as Contract;
use AmcLab\Tenancy\Exceptions\PathfinderException;
use AmcLab\Tenancy\Traits\HasConfigTrait;

class Pathfinder implements Contract {

    use HasConfigTrait;

    public function __construct() {
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

            // versione normalizzata (senza caratteri non alfanumerici)
            'normalized' => $normalized = array_map(function($v){
                return trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $v), '_');
            }, $breadcrumbs),

            // costruisce la catena di pezzi per comporre il resourceId
            'resourceId' => $resourceId = $this->mergeChain('resourceId', $normalized),

            // genera lo stack di chiavi per la cifratura
            'hashable' => $hashable = $this->mergeChain('hashable', $resourceId, true),

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
