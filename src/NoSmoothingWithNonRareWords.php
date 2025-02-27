<?php

namespace olcaytaner\NGram;

class NoSmoothingWithNonRareWords extends NoSmoothing
{
    private array $dictionary;
    private float $probability;

    /**
     * Constructor of {@link NoSmoothingWithNonRareWords}
     *
     * @param int $probability Setter for the probability.
     */
    public function __construct(int $probability){
        $this->probability = $probability;
    }

    /**
     * Wrapper function to set the N-gram probabilities with no smoothing and replacing unknown words not found in nonrare words.
     * @param NGram $nGram N-Gram for which the probabilities will be set.
     * @param int $level Level for which N-Gram probabilities will be set. Probabilities for different levels of the
     *              N-gram can be set with this function. If level = 1, N-Gram is treated as UniGram, if level = 2,
     *              N-Gram is treated as Bigram, etc.
     *
     */
    public function setProbabilitiesWithLevel(NGram $nGram, int $level): void
    {
        $this->dictionary = $nGram->constructDictionaryWithNonRareWords($level, $this->probability);
        $nGram->replaceUnknownWords($this->dictionary);
        parent::setProbabilitiesWithLevel($nGram, $level);
    }
}