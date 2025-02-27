<?php

namespace olcaytaner\NGram;

use Exception;
use olcaytaner\DataStructure\CounterHashMap;

class NGram
{
    public NGramNode $rootNode;
    private int $N;
    private float $lambda1;
    private float $lambda2;
    private bool $interpolated = false;
    private array $vocabulary;
    private array $probabilityOfUnseen;

    /**
     * Constructor of {@link NGram} class which takes a {@link Array} corpus and {@link number} size of ngram as input.
     * It adds all sentences of corpus as ngrams.
     *
     * @param array $args List of the files where NGram is saved.
     */
    public function __construct(array $args, ?int $N = null){
        if ($N != null) {
            $this->N = $N;
            $this->vocabulary = [];
            $this->probabilityOfUnseen = [];
            $this->rootNode = new NGramNode("");
            for ($i = 0; $i < count($args[0]); $i++) {
                $this->addNGramSentence($args[0][$i]);
            }
        } else {
            $multipleFile = new MultipleFile($args);
            $this->readHeader($multipleFile);
            $this->rootNode = new NGramNode(true, $multipleFile);
        }
    }

    /**
     * Reads the header from the input file.
     * @param MultipleFile $multipleFile Input file
     */
    private function readHeader(MultipleFile $multipleFile): void{
        $line = $multipleFile->readLine();
        $items = explode(" ", $line);
        $this->N = (int) $items[0];
        $this->lambda1 = (float) $items[1];
        $this->lambda2 = (float) $items[2];
        $this->probabilityOfUnseen = [];
        $line = $multipleFile->readLine();
        $items = explode(" ", $line);
        for ($i = 0; $i < $this->N; $i++) {
            $this->probabilityOfUnseen[] = $items[$i];
        }
        $this->vocabulary = [];
        $vocabularySize = (int) $multipleFile->readLine();
        for ($i = 0; $i < $vocabularySize; $i++) {
            $this->vocabulary[$multipleFile->readLine()] = null;
        }
    }

    /**
     * Merges current NGram with the given NGram. If N of the two NGram's are not same, it does not
     * merge. Merges first the vocabulary, then the NGram trees.
     * @param NGram $toBeMerged NGram to be merged with.
     */
    public function merge(NGram $toBeMerged): void{
        if ($this->N != $toBeMerged->N) {
            return;
        }
        foreach ($toBeMerged->vocabulary as $item=>$value) {
            $this->vocabulary[$item] = null;
        }
        $this->rootNode->merge($toBeMerged->rootNode);
    }

    /**
     * @return int size of ngram.
     */
    public function getN(): int{
        return $this->N;
    }

    /**
     * Set size of ngram.
     * @param int $N size of ngram
     */
    public function setN(int $N): void{
        $this->N = $N;
    }

    /**
     * Adds {@link Symbol[]} given array of symbols to {@link Set} the vocabulary and to {@link NGramNode} the rootNode
     *
     * @param array $symbols {@link Symbol[]} ngram added.
     */
    public function addNGram(array $symbols): void{
        foreach ($symbols as $item) {
            $this->vocabulary[$item] = null;
        }
        $this->rootNode->addNGram($symbols, 0, $this->N);
    }

    /**
     * Adds given sentence count times to {@link Set} the vocabulary and create and add ngrams of the sentence to
     * {@link NGramNode} the rootNode
     *
     * @param array $symbols {@link Symbol[]} sentence whose ngrams are added.
     * @param int $count Number of times the sentence will be added.
     */
    public function addNGramSentence(array $symbols, int $count = 1): void{
        foreach ($symbols as $item) {
            $this->vocabulary[$item] = null;
        }
        for ($j = 0; $j < count($symbols) - $this->N + 1; $j++) {
            $this->rootNode->addNGram($symbols, $j, $this->N, $count);
        }
    }

    /**
     * @return int vocabulary size.
     */
    public function vocabularySize(): int{
        return count($this->vocabulary);
    }

    /**
     * Sets lambdas, interpolation ratios, for trigram, bigram and unigram probabilities.
     * ie. lambda1 * trigramProbability + lambda2 * bigramProbability  + (1 - lambda1 - lambda2) * unigramProbability
     *
     * @param float $lambda1 interpolation ratio for trigram probabilities
     * @param ?float $lambda2 interpolation ratio for bigram probabilities
     */
    public function setLambda(float $lambda1, ?float $lambda2 = null): void{
        if ($this->N == 2){
            $this->lambda1 = $lambda1;
            $this->interpolated = true;
        } else {
            if ($this->N == 3){
                $this->interpolated = true;
                $this->lambda1 = $lambda1;
                $this->lambda2 = $lambda2;
            }
        }
    }

    /**
     * Calculates NGram probabilities using {@link Array} given corpus and {@link TrainedSmoothing} smoothing method.
     *
     * @param array $corpus corpus for calculating NGram probabilities.
     * @param TrainedSmoothing $trainedSmoothing instance of smoothing method for calculating ngram probabilities.
     */
    public function calculateNGramProbabilitiesTrained(array $corpus, TrainedSmoothing $trainedSmoothing): void{
        $trainedSmoothing->train($corpus, $this);
    }

    /**
     * Calculates NGram probabilities using {@link SimpleSmoothing} simple smoothing.
     *
     * @param SimpleSmoothing $simpleSmoothing {@link SimpleSmoothing}
     */
    public function calculateNGramProbabilitiesSimple(SimpleSmoothing $simpleSmoothing): void{
        $simpleSmoothing->setProbabilities($this);
    }

    public function calculateNGramProbabilitiesSimpleWithLevel(SimpleSmoothing $simpleSmoothing, int $level): void{
        $simpleSmoothing->setProbabilitiesWithLevel($this, $level);
    }

    /**
     * Replaces words not in {@link Set} given dictionary.
     *
     * @param array $dictionary dictionary of known words.
     */
    public function replaceUnknownWords(array $dictionary): void{
        $this->rootNode->replaceUnknownWords($dictionary);
    }

    /**
     * Constructs a dictionary of nonrare words with given N-Gram level and probability threshold.
     *
     * @param int $level Level for counting words. Counts for different levels of the N-Gram can be set. If level = 1, N-Gram is treated as UniGram, if level = 2,
     *              N-Gram is treated as Bigram, etc.
     * @param float $probability probability threshold for nonrare words.
     * @return array {@link HashSet} nonrare words.
     */
    public function constructDictionaryWithNonRareWords(int $level, float $probability): array{
        $result = [];
        $wordCounter = new CounterHashMap();
        $this->rootNode->countWords($wordCounter, $level);
        $sum = $wordCounter->sumOfCounts();
        foreach ($wordCounter->keys() as $key) {
            if ($wordCounter->count($key) / $sum > $probability) {
                $result[] = $key;
            }
        }
        return $result;
    }

    /**
     * Calculates unigram perplexity of given corpus. First sums negative log likelihoods of all unigrams in corpus.
     * Then returns exp of average negative log likelihood.
     *
     * @param array $corpus corpus whose unigram perplexity is calculated.
     *
     * @return float unigram perplexity of corpus.
     */
    private function getUniGramPerplexity(array $corpus): float{
        $sum = 0;
        $count = 0;
        foreach ($corpus as $symbols) {
            foreach ($symbols as $symbol) {
                $p = $this->getProbability($symbol);
                $sum -= log($p);
                $count++;
            }
        }
        return exp($sum / $count);
    }

    /**
     * Calculates bigram perplexity of given corpus. First sums negative log likelihoods of all bigrams in corpus.
     * Then returns exp of average negative log likelihood.
     *
     * @param array $corpus corpus whose bigram perplexity is calculated.
     *
     * @return float bigram perplexity of given corpus.
     */
    private function getBiGramPerplexity(array $corpus): float{
        $sum = 0;
        $count = 0;
        foreach ($corpus as $symbols) {
            for ($j = 0; $j < count($symbols) - 1; $j++) {
                $p = $this->getProbability($symbols[$j], $symbols[$j + 1]);
                $sum -= log($p);
                $count++;
            }
        }
        return exp($sum / $count);
    }

    /**
     * Calculates trigram perplexity of given corpus. First sums negative log likelihoods of all trigrams in corpus.
     * Then returns exp of average negative log likelihood.
     *
     * @param array $corpus corpus whose trigram perplexity is calculated.
     * @return float trigram perplexity of given corpus.
     */
    private function getTriGramPerplexity(array $corpus): float{
        $sum = 0;
        $count = 0;
        foreach ($corpus as $symbols) {
            for ($j = 0; $j < count($symbols) - 2; $j++) {
                $p = $this->getProbability($symbols[$j], $symbols[$j + 1], $symbols[$j + 2]);
                $sum -= log($p);
                $count++;
            }
        }
        return exp($sum / $count);
    }

    /**
     * Calculates the perplexity of given corpus depending on N-Gram model (unigram, bigram, trigram, etc.)
     *
     * @param array $corpus corpus whose perplexity is calculated.
     * @return float perplexity of given corpus
     */
    public function getPerplexity(array $corpus): float{
        return match ($this->N) {
            1 => $this->getUniGramPerplexity($corpus),
            2 => $this->getBiGramPerplexity($corpus),
            3 => $this->getTriGramPerplexity($corpus),
            default => 0,
        };
    }

    /**
     * Gets probability of sequence of symbols depending on N in N-Gram. If N is 1, returns unigram probability.
     * If N is 2, if interpolated is true, then returns interpolated bigram and unigram probability, otherwise returns only bigram probability.
     * If N is 3, if interpolated is true, then returns interpolated trigram, bigram and unigram probability, otherwise returns only trigram probability.
     * @param ... $symbols sequence of symbol.
     * @return float probability of given sequence.
     */
    public function getProbability(... $symbols): float{
        switch ($this->N) {
            case 1:
                return $this->getUniGramProbability($symbols[0]);
            case 2:
                if (count($symbols) == 1) {
                    return $this->getUniGramProbability($symbols[0]);
                }
                if ($this->interpolated){
                    return $this->lambda1 * $this->getBiGramProbability($symbols[0], $symbols[1])
                        + (1 - $this->lambda1) * $this->getUniGramProbability($symbols[1]);
                } else {
                    return $this->getBiGramProbability($symbols[0], $symbols[1]);
                }
            case 3:
                if (count($symbols) == 1) {
                    return $this->getUniGramProbability($symbols[0]);
                } else {
                    if (count($symbols) == 2) {
                        return $this->getBiGramProbability($symbols[0], $symbols[1]);
                    }
                }
                if ($this->interpolated){
                    return $this->lambda1 * $this->getTriGramProbability($symbols[0], $symbols[1], $symbols[2])
                        + $this->lambda2 * $this->getBiGramProbability($symbols[1], $symbols[2])
                        + (1 - $this->lambda1 - $this->lambda2) * $this->getUniGramProbability($symbols[2]);
                } else {
                    return $this->getTriGramProbability($symbols[0], $symbols[1], $symbols[2]);
                }
        }
        return 0.0;
    }

    /**
     * Gets unigram probability of given symbol.
     * @param string $w1 a unigram symbol.
     * @return float probability of given unigram.
     */
    private function getUniGramProbability(string $w1): float{
        return $this->rootNode->getUniGramProbability($w1);
    }

    /**
     * Gets bigram probability of given symbols.
     * @param string $w1 first gram of bigram
     * @param string $w2 second gram of bigram
     * @return float probability of bigram formed by w1 and w2.
     */
    private function getBiGramProbability(string $w1, string $w2): float{
        try{
            return $this->rootNode->getBiGramProbability($w1, $w2);
        } catch (Exception $e){
            return $this->probabilityOfUnseen[1];
        }
    }

    /**
     * Gets trigram probability of given symbols.
     * @param string $w1 first gram of trigram
     * @param string $w2 second gram of trigram
     * @param string $w3 third gram of trigram
     * @return float probability of trigram formed by w1, w2, w3.
     */
    private function getTriGramProbability(string $w1, string $w2, string $w3): float{
        try{
            return $this->rootNode->getTriGramProbability($w1, $w2, $w3);
        } catch (Exception $e){
            return $this->probabilityOfUnseen[2];
        }
    }

    /**
     * Gets count of given sequence of symbol.
     * @param array $symbols sequence of symbol.
     * @return int count of symbols.
     */
    public function getCount(array $symbols): int{
        return $this->rootNode->getCountForSymbols($symbols, 0);
    }

    /**
     * Sets probabilities by adding pseudocounts given height and pseudocount.
     * @param float  $pseudoCount pseudocount added to all N-Grams.
     * @param int $height  height for N-Gram. If height= 1, N-Gram is treated as UniGram, if height = 2,
     *                N-Gram is treated as Bigram, etc.
     */
    public function setProbabilityWithPseudoCount(float $pseudoCount, int $height): void
    {
        if ($pseudoCount != 0){
            $vocabularySize = $this->vocabularySize() + 1;
        } else {
            $vocabularySize = $this->vocabularySize();
        }
        $this->rootNode->setProbabilityWithPseudoCount($pseudoCount, $height, $vocabularySize);
        if ($pseudoCount != 0){
            $this->probabilityOfUnseen[$height - 1] = 1.0 / $vocabularySize;
        } else {
            $this->probabilityOfUnseen[$height - 1] = 0.0;
        }
    }

    /**
     * Find maximum occurrence in given height.
     * @param int $height height for occurrences. If height = 1, N-Gram is treated as UniGram, if height = 2,
     *               N-Gram is treated as Bigram, etc.
     * @return int maximum occurrence in given height.
     */
    private function maximumOccurrence(int $height): int{
        return $this->rootNode->maximumOccurrence($height);
    }

    /**
     * Update counts of counts of N-Grams with given counts of counts and given height.
     * @param array $countsOfCounts updated counts of counts.
     * @param int $height  height for NGram. If height = 1, N-Gram is treated as UniGram, if height = 2,
     *                N-Gram is treated as Bigram, etc.
     */
    private function updateCountsOfCounts(array &$countsOfCounts, int $height): void{
        $this->rootNode->updateCountsOfCounts($countsOfCounts, $height);
    }

    /**
     * Calculates counts of counts of NGrams.
     * @param int $height  height for NGram. If height = 1, N-Gram is treated as UniGram, if height = 2,
     *                N-Gram is treated as Bigram, etc.
     * @return array counts of counts of NGrams.
     */
    public function calculateCountsOfCounts(int $height): array{
        $maxCount = $this->maximumOccurrence($height);
        $countsOfCounts = [];
        for ($i = 0; $i < $maxCount + 2; $i++){
            $countsOfCounts[] = 0;
        }
        $this->updateCountsOfCounts($countsOfCounts, $height);
        return $countsOfCounts;
    }

    /**
     * Sets probability with given counts of counts and pZero.
     * @param array $countsOfCounts counts of counts of NGrams.
     * @param int $height  height for NGram. If height = 1, N-Gram is treated as UniGram, if height = 2,
     *                N-Gram is treated as Bigram, etc.
     * @param float $pZero probability of zero.
     */
    public function setAdjustedProbability(array $countsOfCounts, int $height, float $pZero): void{
        $this->rootNode->setAdjustedProbability($countsOfCounts, $height, $this->vocabularySize() + 1, $pZero);
        $this->probabilityOfUnseen[$height - 1] = 1.0 / ($this->vocabularySize() + 1);
    }

    /**
     * Prunes NGram according to the given threshold. All nodes having a probability less than the threshold will be
     * pruned.
     * @param float $threshold Probability threshold used for pruning.
     */
    public function prune(float $threshold): void{
        if ($threshold > 0 && $threshold <= 1.0){
            $this->rootNode->prune($threshold, $this->N - 1);
        }
    }
}