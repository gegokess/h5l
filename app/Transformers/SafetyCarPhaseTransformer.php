<?php

namespace App\Transformers;

use League\Fractal\TransformerAbstract;
use App\Models\SafetyCarPhase;

/**
 * SafetyCarPhaseTransformer
 */
class SafetyCarPhaseTransformer extends TransformerAbstract
{
   /**
     * List of resources to automatically include
     *
     * @var array
     */
    protected $defaultIncludes = [

    ];

    /**
     * List of resources possible to include
     *
     * @var array
     */
    protected $availableIncludes = [
        'session'
    ];

    public function transform (SafetyCarPhase $safetyCarPhase) {
        return [
            'start' => (int) $safetyCarPhase->start,
            'end' => (int) $safetyCarPhase->end,
            'isVirtual' => (boolean) $safetyCarPhase->virtualSC
        ];
    }

    public function includeSession (SafetyCarPhase $safetyCarPhase) {
        $session = $safetyCarPhase->session;

        return $this->item($session, new SessionTransformer);
    }

}