<?php

namespace olcaytaner\NGram;

use olcaytaner\Sampling\KFoldCrossValidation;

class InterpolatedSmoothing extends TrainedSmoothing
{
    private float $lambda1;
    private float $lambda2;
    private SimpleSmoothing $simpleSmoothing;

    public function __construct(SimpleSmoothing $simpleSmoothing = new GoodTuringSmoothing())
    {
        $this->simpleSmoothing = $simpleSmoothing;
    }

    /**
     * The algorithm tries to optimize the best lambda for a given corpus. The algorithm uses perplexity on the validation
     * set as the optimization criterion.
     *
     * @param array $nGrams 10 N-Grams learned for different folds of the corpus. nGrams[i] is the N-Gram trained with i'th train fold of the corpus.
     * @param KFoldCrossValidation $kFoldCrossValidation Cross-validation data used in training and testing the N-grams.
     * @param float $lowerBound Initial lower bound for optimizing the best lambda.
     * @return float Best lambda optimized with k-fold crossvalidation.
     */
    private function learnBestLambda(array                $nGrams,
                                     KFoldCrossValidation $kFoldCrossValidation,
                                     float                $lowerBound): float
    {
        $bestPrevious = -1;
        $upperBound = 0.999;
        $bestLambda = ($lowerBound + $upperBound) / 2;
        $numberOfParts = 5;
        $testFolds = [];
        for ($i = 0; $i < 10; $i++) {
            $testFolds[] = $kFoldCrossValidation->getTestFold($i);
        }
        while (true) {
            $bestPerplexity = PHP_FLOAT_MAX;
            for ($value = $lowerBound; $value <= $upperBound; $value += ($upperBound - $lowerBound) / $numberOfParts) {
                $perplexity = 0;
                for ($i = 0; $i < 10; $i++) {
                    $nGrams[$i]->setLambda($value);
                    $perplexity += $nGrams[$i]->getPerplexity($testFolds[$i]);
                }
                if ($perplexity < $bestPerplexity) {
                    $bestPerplexity = $perplexity;
                    $bestLambda = $value;
                }
            }
            $lowerBound = $this->newLowerBound($bestLambda, $lowerBound, $upperBound, $numberOfParts);
            $upperBound = $this->newUpperBound($bestLambda, $lowerBound, $upperBound, $numberOfParts);
            if ($bestPrevious != -1) {
                if (abs($bestPrevious - $bestPerplexity) / $bestPerplexity < 0.001) {
                    break;
                }
            }
            $bestPrevious = $bestPerplexity;
        }
        return $bestLambda;
    }

    /**
     * The algorithm tries to optimize the best lambdas (lambda1, lambda2) for a given corpus. The algorithm uses perplexity on the validation
     * set as the optimization criterion.
     *
     * @param array $nGrams 10 N-Grams learned for different folds of the corpus. nGrams[i] is the N-Gram trained with i'th train fold of the corpus.
     * @param KFoldCrossValidation $kFoldCrossValidation Cross-validation data used in training and testing the N-grams.
     * @param float $lowerBound1 Initial lower bound for optimizing the best lambda1.
     * @param float $lowerBound2 Initial lower bound for optimizing the best lambda2.
     */
    private function learnBestLambdas(array                $nGrams,
                                      KFoldCrossValidation $kFoldCrossValidation,
                                      float                $lowerBound1,
                                      float                $lowerBound2): array
    {
        $bestPrevious = -1;
        $upperBound1 = 0.999;
        $upperBound2 = 0.999;
        $bestLambda1 = ($lowerBound1 + $upperBound1) / 2;
        $bestLambda2 = ($lowerBound2 + $upperBound2) / 2;
        $numberOfParts = 5;
        $testFolds = [];
        for ($i = 0; $i < 10; $i++) {
            $testFolds[] = $kFoldCrossValidation->getTestFold($i);
        }
        while (true) {
            $bestPerplexity = PHP_FLOAT_MAX;
            for ($value1 = $lowerBound1; $value1 <= $upperBound1; $value1 += ($upperBound1 - $lowerBound1) / $numberOfParts) {
                for ($value2 = $lowerBound1; $value2 <= $upperBound1; $value2 += ($upperBound1 - $lowerBound1) / $numberOfParts) {
                    $perplexity = 0;
                    for ($i = 0; $i < 10; $i++) {
                        $nGrams[$i]->setLambda($value1, $value2);
                        $perplexity += $nGrams[$i]->getPerplexity($testFolds[$i]);
                    }
                    if ($perplexity < $bestPerplexity) {
                        $bestPerplexity = $perplexity;
                        $bestLambda1 = $value1;
                        $bestLambda2 = $value2;
                    }
                }
            }
            $lowerBound1 = $this->newLowerBound($bestLambda1, $lowerBound1, $upperBound1, $numberOfParts);
            $upperBound1 = $this->newUpperBound($bestLambda1, $lowerBound1, $upperBound1, $numberOfParts);
            $lowerBound2 = $this->newLowerBound($bestLambda2, $lowerBound2, $upperBound2, $numberOfParts);
            $upperBound2 = $this->newUpperBound($bestLambda2, $lowerBound2, $upperBound2, $numberOfParts);
            if ($bestPrevious != -1) {
                if (abs($bestPrevious - $bestPerplexity) / $bestPerplexity < 0.001) {
                    break;
                }
            }
            $bestPrevious = $bestPerplexity;
        }
        return [$bestLambda1, $bestLambda2];
    }

    /**
     * Wrapper function to set the N-gram probabilities with interpolated smoothing.
     * @param NGram $nGram N-Gram for which the probabilities will be set.
     * @param int $level Level for which N-Gram probabilities will be set. Probabilities for different levels of the
     *              N-gram can be set with this function. If level = 1, N-Gram is treated as UniGram, if level = 2,
     *              N-Gram is treated as Bigram, etc.
     *
     */
    public function setProbabilitiesWithLevel(NGram $nGram, int $level): void
    {
        for ($j = 2; $j <= $nGram->getN(); $j++) {
            $nGram->calculateNGramProbabilitiesSimpleWithLevel($this->simpleSmoothing, $j);
        }
        $nGram->calculateNGramProbabilitiesSimpleWithLevel($this->simpleSmoothing, 1);
        switch ($nGram->getN()) {
            case 2:
                $nGram->setLambda($this->lambda1);
                break;
            case 3:
                $nGram->setLambda($this->lambda1, $this->lambda2);
                break;
        }
    }

    /**
     * Wrapper function to learn the parameters (lambda1 and lambda2) in interpolated smoothing. The function first creates K NGrams
     * with the train folds of the corpus. Then optimizes lambdas with respect to the test folds of the corpus depending on given N.
     * @param array $corpus Train corpus used to optimize lambda parameters
     * @param int $N N in N-Gram.
     */
    protected function learnParameters(array $corpus, int $N): void
    {
        if ($N <= 1) {
            return;
        }
        $K = 10;
        $nGrams = [];
        $kFoldCrossValidation = new KFoldCrossValidation($corpus, $K, 0);
        for ($i = 0; $i < $K; $i++) {
            $nGrams[$i] = new NGram($kFoldCrossValidation->getTrainFold($i), $N);
            for ($j = 2; $j <= $N; $j++) {
                $nGrams[$i]->calculateNGramProbabilitiesSimpleWithLevel($this->simpleSmoothing, $j);
            }
            $nGrams[$i]->calculateNGramProbabilitiesSimpleWithLevel($this->simpleSmoothing, 1);
        }
        if ($N == 2) {
            $this->lambda1 = $this->learnBestLambda($nGrams, $kFoldCrossValidation, 0.1);
        } else {
            if ($N == 3) {
                $bestLambdas = $this->learnBestLambdas($nGrams, $kFoldCrossValidation, 0.1, 0.1);
                $this->lambda1 = $bestLambdas[0];
                $this->lambda2 = $bestLambdas[1];
            }
        }
    }
}