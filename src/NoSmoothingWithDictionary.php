<?php

namespace olcaytaner\NGram;

class NoSmoothingWithDictionary extends NoSmoothing
{
    private array $dictionary;

    /**
     * Constructor of {@link NoSmoothingWithDictionary}
     * @param array $dictionary Dictionary to use in smoothing
     */
    public function __construct(array $dictionary){
        $this->dictionary = $dictionary;
    }

    /**
     * Wrapper function to set the N-gram probabilities with no smoothing and replacing unknown words not found in {@link HashSet} the dictionary.
     * @param NGram $nGram N-Gram for which the probabilities will be set.
     * @param int $level Level for which N-Gram probabilities will be set. Probabilities for different levels of the
     *              N-gram can be set with this function. If level = 1, N-Gram is treated as UniGram, if level = 2,
     *              N-Gram is treated as Bigram, etc.
     */
    public function setProbabilitiesWithLevel(NGram $nGram, int $level): void
    {
        $nGram->replaceUnknownWords($this->dictionary);
        parent::setProbabilitiesWithLevel($nGram, $level);
    }
}