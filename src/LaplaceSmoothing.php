<?php

namespace olcaytaner\NGram;

class LaplaceSmoothing extends SimpleSmoothing
{
    private float $delta;

    /**
     * Constructor for Laplace smoothing. Sets the delta.
     * @param float $delta Delta value in Laplace smoothing.
     */
    public function __construct(float $delta = 1.0){
        $this->delta = $delta;
    }

    /**
     * Wrapper function to set the N-gram probabilities with laplace smoothing.
     *
     * @param NGram $nGram N-Gram for which the probabilities will be set.
     * @param int $level Level for which N-Gram probabilities will be set. Probabilities for different levels of the
     *              N-gram can be set with this function. If level = 1, N-Gram is treated as UniGram, if level = 2,
     *              N-Gram is treated as Bigram, etc.
     */
    public function setProbabilitiesWithLevel(NGram $nGram, int $level): void
    {
        $nGram->setProbabilityWithPseudoCount($this->delta, $level);
    }
}