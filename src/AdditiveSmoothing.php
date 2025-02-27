<?php

namespace olcaytaner\NGram;

use olcaytaner\Sampling\KFoldCrossValidation;

class AdditiveSmoothing extends TrainedSmoothing
{
    /**
     * Additive pseudocount parameter used in Additive Smoothing. The parameter will be learned using 10-fold cross
     * validation.
     */
    private float $delta;

    /**
     * Gets the best delta.
     * @return float Learned best delta.
     */
    public function getDelta(): float
    {
        return $this->delta;
    }

    /**
     * The algorithm tries to optimize the best delta for a given corpus. The algorithm uses perplexity on the validation
     * set as the optimization criterion.
     * @param array $nGrams 10 N-Grams learned for different folds of the corpus. nGrams[i] is the N-Gram trained with i'th train
     *               fold of the corpus.
     * @param KFoldCrossValidation $kFoldCrossValidation Cross-validation data used in training and testing the N-grams.
     * @param float $lowerBound Initial lower bound for optimizing the best delta.
     * @return float Best delta optimized with k-fold crossvalidation.
     */
    private function learnBestDelta(array $nGrams, KFoldCrossValidation $kFoldCrossValidation, float $lowerBound): float{
        $bestPrevious = -1;
        $upperBound = 1;
        $bestDelta = ($lowerBound + $upperBound) / 2;
        $numberOfParts = 5;
        while (true){
            $bestPerplexity = PHP_FLOAT_MAX;
            for ($value = $lowerBound; $value <= $upperBound; $value += ($upperBound - $lowerBound) / $numberOfParts){
                $perplexity = 0;
                for ($i = 0; $i < 10; $i++){
                    $nGrams[$i]->setProbabilityWithPseudoCount($value, $nGrams[$i]->getN());
                    $perplexity += $nGrams[$i]->getPerplexity($kFoldCrossValidation->getTestFold($i));
                }
                if ($perplexity < $bestPerplexity){
                    $bestPerplexity = $perplexity;
                    $bestDelta = $value;
                }
            }
            $lowerBound = $this->newLowerBound($bestDelta, $lowerBound, $upperBound, $numberOfParts);
            $upperBound = $this->newUpperBound($bestDelta, $lowerBound, $upperBound, $numberOfParts);
            if ($bestPrevious != -1){
                if (abs($bestPrevious - $bestPerplexity) / $bestPerplexity < 0.001){
                    break;
                }
            }
            $bestPrevious = $bestPerplexity;
        }
        return $bestDelta;
    }

    /**
     * Wrapper function to set the N-gram probabilities with additive smoothing.
     * @param NGram $nGram N-Gram for which the probabilities will be set.
     * @param int $level Level for which N-Gram probabilities will be set. Probabilities for different levels of the
     *              N-gram can be set with this function. If level = 1, N-Gram is treated as UniGram, if level = 2,
     *              N-Gram is treated as Bigram, etc.
     */
    public function setProbabilitiesWithLevel(NGram $nGram, int $level): void
    {
        $nGram->setProbabilityWithPseudoCount($this->delta, $level);
    }

    /**
     * Wrapper function to learn the parameter (delta) in additive smoothing. The function first creates K NGrams
     * with the train folds of the corpus. Then optimizes delta with respect to the test folds of the corpus.
     * @param array $corpus Train corpus used to optimize delta parameter
     * @param int $N N in N-Gram.
     */
    protected function learnParameters(array $corpus, int $N): void
    {
        $K = 10;
        $nGrams = [];
        $kFoldCrossValidation = new KFoldCrossValidation($corpus, $K, 0);
        for ($i = 0; $i < $N; $i++){
            $nGrams[] = new NGram($kFoldCrossValidation->getTrainFold($i), $N);
        }
        $this->delta = $this->learnBestDelta($nGrams, $kFoldCrossValidation, 0.1);
    }

}