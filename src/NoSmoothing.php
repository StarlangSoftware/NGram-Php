<?php

namespace olcaytaner\NGram;

class NoSmoothing extends SimpleSmoothing
{

    /**
     * Calculates the N-Gram probabilities with no smoothing
     * @param NGram $nGram N-Gram for which no smoothing is done.
     * @param int $level Height of the NGram node.
     */
    public function setProbabilitiesWithLevel(NGram $nGram, int $level): void
    {
        $nGram->setProbabilityWithPseudoCount(0.0, $level);
    }
}