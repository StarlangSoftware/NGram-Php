<?php

namespace olcaytaner\NGram;

abstract class TrainedSmoothing extends SimpleSmoothing
{
    protected abstract function learnParameters(array $corpus, int $N): void;

    /**
     * Calculates new lower bound.
     * @param float $current current value.
     * @param float $currentLowerBound current lower bound
     * @param float $currentUpperBound current upper bound
     * @param int $numberOfParts number of parts between lower and upper bound.
     * @return float new lower bound
     */
    protected function newLowerBound(float $current, float $currentLowerBound, float $currentUpperBound, int $numberOfParts): float{
        if ($current != $currentLowerBound){
            return $current - ($currentUpperBound - $currentLowerBound) / $numberOfParts;
        } else {
            return $current / $numberOfParts;
        }
    }

    /**
     * Calculates new upper bound.
     * @param float $current current value.
     * * @param float $currentLowerBound current lower bound
     * * @param float $currentUpperBound current upper bound
     * * @param int $numberOfParts number of parts between lower and upper bound.
     * @return float new upper bound
     */
    protected function newUpperBound(float $current, float $currentLowerBound, float $currentUpperBound, int $numberOfParts): float
    {
        if ($current != $currentUpperBound){
            return $current + ($currentUpperBound - $currentLowerBound) / $numberOfParts;
        } else {
            return $current * $numberOfParts;
        }
    }

    /**
     * Wrapper function to learn parameters of the smoothing method and set the N-gram probabilities.
     *
     * @param array $corpus Train corpus used to optimize parameters of the smoothing method.
     * @param NGram $nGram N-Gram for which the probabilities will be set.
     */
    public function train(array $corpus, NGram $nGram): void{
        $this->learnParameters($corpus, $nGram->getN());
        $this->setProbabilities($nGram);
    }
}