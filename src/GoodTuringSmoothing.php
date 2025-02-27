<?php

namespace olcaytaner\NGram;

use olcaytaner\Math\Matrix;
use olcaytaner\Math\Vector;

class GoodTuringSmoothing extends SimpleSmoothing
{

    /**
     * Given counts of counts, this function will calculate the estimated counts of counts c$^*$ with
     * Good-Turing smoothing. First, the algorithm filters the non-zero counts from counts of counts array and constructs
     * $c and $r arrays. Then it constructs Z_n array with Z_n = (2C_n / (r_{n+1} - r_{n-1})). The algorithm then uses
     * simple linear regression on Z_n values to estimate w_1 and w_0, where log(N[$i]) = w_1log($i) + w_0
     * @param array $countsOfCounts Counts of counts. $countsOfCounts[1] is the number of words occurred once in the corpus.
     *                       $countsOfCounts[$i] is the number of words occurred $i times in the corpus.
     * @return array Estimated counts of counts array. N[1] is the estimated count for out of vocabulary words.
     */
    private function linearRegressionOnCountsOfCounts(array $countsOfCounts): array
    {
        $N = [];
        $r = [];
        $c = [];
        for ($i = 1; $i < count($countsOfCounts); $i++) {
            if ($countsOfCounts[$i] != 0) {
                $r[] = $i;
                $c[] = $countsOfCounts[$i];
            }
        }
        $A = new Matrix(2, 2);
        $y = new Vector(2, 0);
        for ($i = 0; $i < count($r); $i++) {
            $xt = log($r[$i]);
            if ($i == 0) {
                $rt = log($c[$i]);
            } else {
                if ($i == count($r) - 1) {
                    $rt = log($c[$i] / ($r[$i] - $r[$i - 1]));
                } else {
                    $rt = log((2.0 * $c[$i]) / ($r[$i + 1] - $r[$i - 1]));
                }
            }
            $A->addValue(0, 0, 1.0);
            $A->addValue(0, 1, $xt);
            $A->addValue(1, 0, $xt);
            $A->addValue(1, 1, $xt * $xt);
            $y->addValue(0, $rt);
            $y->addValue(1, $rt * $xt);
        }
        $A->inverse();
        $w = $A->multiplyWithVectorFromRight($y);
        $w0 = $w->getValue(0);
        $w1 = $w->getValue(1);
        for ($i = 1; $i < count($countsOfCounts); $i++) {
            $N[$i] = exp(log($i) * $w1 + $w0);
        }
        return $N;
    }

    /**
     * Wrapper function to set the N-gram probabilities with Good-Turing smoothing. N[1] / \sum_{i=1}^infty N_i is
     * the out of vocabulary probability.
     * @param NGram $nGram N-Gram for which the probabilities will be set.
     * @param int $level Level for which N-Gram probabilities will be set. Probabilities for different levels of the
     *              N-gram can be set with this function. If level = 1, N-Gram is treated as UniGram, if level = 2,
     *              N-Gram is treated as Bigram, etc.
     */
    public function setProbabilitiesWithLevel(NGram $nGram, int $level): void
    {
        $countsOfCounts = $nGram->calculateCountsOfCounts($level);
        $N = $this->linearRegressionOnCountsOfCounts($countsOfCounts);
        $sum = 0;
        for ($r = 1; $r < count($countsOfCounts); $r++) {
            $sum += $countsOfCounts[$r] * $r;
        }
        $nGram->setAdjustedProbability($N, $level, $N[1] / $sum);
    }
}