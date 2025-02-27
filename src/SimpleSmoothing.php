<?php

namespace olcaytaner\NGram;

abstract class SimpleSmoothing
{
    public abstract function setProbabilitiesWithLevel(NGram $nGram, int $level): void;

    /**
     * Calculates the N-Gram probabilities with simple smoothing.
     * @param NGram $nGram N-Gram for which simple smoothing calculation is done.
     */
    public function setProbabilities(NGram $nGram): void{
        $this->setProbabilitiesWithLevel($nGram, $nGram->getN());
    }
}