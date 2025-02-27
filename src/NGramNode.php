<?php

namespace olcaytaner\NGram;

use Exception;
use olcaytaner\DataStructure\CounterHashMap;

class NGramNode
{
    private ?array $children = null;
    private string $symbol = "";
    private int $count;
    private float $probability;
    private float $probabilityOfUnseen;
    private ?NGramNode $unknown = null;

    public function constructor1(string $symbol): void
    {
        $this->symbol = $symbol;
        $this->count = 0;
    }

    public function constructor2(string|bool $symbol, MultipleFile $multipleFile): void
    {
        if (!$symbol){
            $this->symbol = trim($multipleFile->readLine());
        }
        $line = trim($multipleFile->readLine());
        $items = explode(" ", $line);
        $this->count = (int) $items[0];
        $this->probability = (float) $items[1];
        $this->probabilityOfUnseen = (float) $items[2];
        $numberOfChildren = (int) $items[3];
        if ($numberOfChildren > 0){
            $this->children = [];
            for ($i = 0; $i < $numberOfChildren; $i++){
                $childNode = new NGramNode(false, $multipleFile);
                $this->children[$childNode->symbol] = $childNode;
            }
        }
    }

    /**
     * Constructor of {@link NGramNode}
     *
     * @param string|bool $symbol symbol to be kept in this node.
     * @param MultipleFile|null $multipleFile MultipleFile Multiple file structure to read the nGram.
     */
    public function __construct(string|bool $symbol, ?MultipleFile $multipleFile = null){
        if ($multipleFile === null){
            $this->constructor1($symbol);
        } else {
            $this->constructor2($symbol, $multipleFile);
        }
    }

    /**
     * Merges this NGramNode with the corresponding NGramNode in another NGram.
     * @param NGramNode $toBeMerged Parallel NGramNode of the parallel NGram tree.
     */
    public function merge(NGramNode $toBeMerged): void{
        if ($this->children != null){
            foreach ($this->children as $symbol => $value){
                if (isset($toBeMerged->children[$symbol])){
                    $this->children[$symbol]->merge($toBeMerged->children[$symbol]);
                }
            }
            foreach ($toBeMerged->children as $symbol => $value){
                if (!isset($this->children[$symbol])){
                    $this->children[$symbol] = $toBeMerged->children[$symbol];
                }
            }
        }
        $this->count += $toBeMerged->count;
    }

    /**
     * Gets count of this node.
     *
     * @return int count of this node.
     */
    public function getCount(): int{
        return $this->count;
    }

    /**
     * Gets the size of children of this node.
     *
     * @return int size of children of {@link NGramNode} this node.
     */
    public function size(): int{
        return count($this->children);
    }

    /**
     * Finds maximum occurrence. If height is 0, returns the count of this node.
     * Otherwise, traverses this nodes' children recursively and returns maximum occurrence.
     *
     * @param int $height height for NGram.
     * @return int maximum occurrence.
     */
    public function maximumOccurrence(int $height): int{
        $max = 0;
        if ($height == 0){
            return $this->count;
        } else {
            foreach ($this->children as $child){
                $current = $child->maximumOccurrence($height - 1);
                if ($current > $max){
                    $max = $current;
                }
            }
            return $max;
        }
    }

    /**
     * @return int sum of counts of children nodes.
     */
    public function childSum(): int{
        $sum = 0;
        foreach ($this->children as $child){
            $sum += $child->count;
        }
        if ($this->unknown != null){
            $sum += $this->unknown->count;
        }
        return $sum;
    }

    /**
     * Traverses nodes and updates counts of counts for each node.
     *
     * @param array $countsOfCounts counts of counts of NGrams.
     * @param int $height         height for NGram. if height = 1, If level = 1, N-Gram is treated as UniGram, if level = 2,
     *                       N-Gram is treated as Bigram, etc.
     */
    public function updateCountsOfCounts(array &$countsOfCounts, int $height): void{
        if ($height == 0){
            $countsOfCounts[$this->count]++;
        } else {
            foreach ($this->children as $child){
                $child->updateCountsOfCounts($countsOfCounts, $height - 1);
            }
        }
    }

    /**
     * Sets probabilities by traversing nodes and adding pseudocount for each NGram.
     *
     * @param float $pseudoCount    pseudocount added to each NGram.
     * @param int $height         height for NGram. if height = 1, If level = 1, N-Gram is treated as UniGram, if level = 2,
     *                       N-Gram is treated as Bigram, etc.
     * @param int $vocabularySize size of vocabulary
     */
    public function setProbabilityWithPseudoCount(float $pseudoCount, int $height, int $vocabularySize): void{
        if ($height == 1){
            $sum = $this->childSum() + $pseudoCount * $vocabularySize;
            foreach ($this->children as $child){
                $child->probability = ($child->count + $pseudoCount) / $sum;
            }
            if ($this->unknown != null){
                $this->unknown->probability = ($this->unknown->count + $pseudoCount) / $sum;
            }
            $this->probabilityOfUnseen = $pseudoCount / $sum;
        } else {
            foreach ($this->children as $child){
                $child->setProbabilityWithPseudoCount($pseudoCount, $height - 1, $vocabularySize);
            }
        }
    }

    /**
     * Sets adjusted probabilities with counts of counts of NGrams.
     * For count < 5, count is considered as ((r + 1) * N[r + 1]) / N[r]), otherwise, count is considered as it is.
     * Sum of children counts are computed. Then, probability of a child node is (1 - pZero) * (r / sum) if r > 5
     * otherwise, r is replaced with ((r + 1) * N[r + 1]) / N[r]) and calculated the same.
     *
     * @param array $N              counts of counts of NGrams.
     * @param int $height         height for NGram. if height = 1, If level = 1, N-Gram is treated as UniGram, if level = 2,
     *                       N-Gram is treated as Bigram, etc.
     * @param int $vocabularySize size of vocabulary.
     * @param float $pZero          probability of zero.
     */
    public function setAdjustedProbability(array $N, int $height, int $vocabularySize, float $pZero): void{
        if ($height == 1){
            $sum = 0;
            foreach ($this->children as $child){
                $r = $child->count;
                if ($r <= 5){
                    $newR = (($r + 1) * $N[$r + 1]) / $N[$r];
                    $sum += $newR;
                } else {
                    $sum += $r;
                }
            }
            foreach ($this->children as $child){
                $r = $child->count;
                if ($r <= 5){
                    $newR = (($r + 1) * $N[$r + 1]) / $N[$r];
                    $child->probability = (1 - $pZero) * ($newR / $sum);
                } else {
                    $child->probability = (1 - $pZero) * ($r / $sum);
                }
            }
            $this->probabilityOfUnseen = $pZero / ($vocabularySize - count($this->children));
        } else {
            foreach ($this->children as $child){
                $child->setAdjustedProbability($N, $height - 1, $vocabularySize, $pZero);
            }
        }
    }

    /**
     * Adds count times NGram given as array of symbols to the node as a child.
     *
     * @param array $s array of symbols
     * @param int $index start index of NGram
     * @param int $height height for NGram. if height = 1, If level = 1, N-Gram is treated as UniGram, if level = 2,
     *               N-Gram is treated as Bigram, etc.
     * @param int|null $count Number of times this NGram is added.
     */
    public function addNGram(array $s, int $index, int $height, int $count = null): void{
        if ($count == null){
            $this->addNGram($s, $index, $height, 1);
        } else {
            if ($height == 0){
                return;
            }
            $symbol = $s[$index];
            if ($this->children != null && isset($this->children[$symbol])){
                $child = $this->children[$symbol];
            } else {
                $child = new NGramNode($symbol);
                if ($this->children == null){
                    $this->children = [];
                }
                $this->children[$symbol] = $child;
            }
            $child->count += $count;
            $child->addNGram($s, $index + 1, $height - 1, $count);
        }
    }

    /**
     * Gets unigram probability of given symbol.
     *
     * @param string $w1 unigram.
     * @return float unigram probability of given symbol.
     */
    public function getUniGramProbability(string $w1): float{
        if (isset($this->children[$w1])){
            return $this->children[$w1]->probability;
        } else {
            if ($this->unknown != null){
                return $this->unknown->probability;
            }
            return $this->probabilityOfUnseen;
        }
    }

    /**
     * Gets bigram probability of given symbols w1 and w2
     *
     * @param string $w1 first gram of bigram.
     * @param string $w2 second gram of bigram.
     * @return float probability of given bigram
     * @throws Exception
     */
    public function getBiGramProbability(string $w1, string $w2): float{
        if (isset($this->children[$w1])){
            $child = $this->children[$w1];
            return $child->getUniGramProbability($w2);
        } else {
            if ($this->unknown != null){
                return $this->unknown->getUniGramProbability($w2);
            }
            throw new Exception("UnseenCase");
        }
    }

    /**
     * Gets trigram probability of given symbols w1, w2 and w3.
     *
     * @param string $w1 first gram of trigram
     * @param string $w2 second gram of trigram
     * @param string $w3 third gram of trigram
     * @return float probability of given trigram.
     * @throws Exception
     */
    public function getTriGramProbability(string $w1, string $w2, string $w3): float{
        if (isset($this->children[$w1])){
            $child = $this->children[$w1];
            return $child->getBiGramProbability($w2, $w3);
        } else {
            if ($this->unknown != null){
                return $this->unknown->getBiGramProbability($w2, $w3);
            }
            throw new Exception("UnseenCase");
        }
    }

    /**
     * Counts words recursively given height and wordCounter.
     *
     * @param CounterHashMap $wordCounter word counter keeping symbols and their counts.
     * @param int $height      height for NGram. if height = 1, If level = 1, N-Gram is treated as UniGram, if level = 2,
     *                    N-Gram is treated as Bigram, etc.
     */
    public function countWords(CounterHashMap $wordCounter, int $height): void{
        if ($height == 0){
            $wordCounter->putNTimes($this->symbol, $this->count);
        } else {
            foreach ($this->children as $child){
                $child->countWords($wordCounter, $height - 1);
            }
        }
    }

    /**
     * Replace words not in given dictionary.
     * Deletes unknown words from children nodes and adds them to {@link NGramNode#unknown} unknown node as children recursively.
     *
     * @param array $dictionary dictionary of known words.
     */
    public function replaceUnknownWords(array $dictionary): void{
        if ($this->children != null){
            $childList = [];
            foreach ($this->children as $symbol => $value){
                if (!isset($dictionary[$symbol])){
                    $childList[] = $this->children[$symbol];
                }
            }
            if (count($childList) > 0){
                $unknown = new NGramNode("");
                $unknown->children = [];
                $sum = 0;
                foreach ($childList as $child){
                    if ($child->children != null){
                        foreach ($child->children as $symbol => $value){
                            $unknown->children[$symbol] = $child->children[$symbol];
                        }
                    }
                    $sum += $child->count;
                    unset($this->children[$child->symbol]);
                }
                $unknown->count = $sum;
                $unknown->replaceUnknownWords($dictionary);
            }
            foreach ($this->children as $child){
                $child->replaceUnknownWords($dictionary);
            }
        }
    }

    /**
     * Gets count of symbol given array of symbols and index of symbol in this array.
     *
     * @param array $s     array of symbols
     * @param int $index index of symbol whose count is returned
     * @return int count of the symbol.
     */
    public function getCountForSymbols(array $s, int $index): int{
        if ($index < count($s)){
            if (isset($this->children[$s[$index]])){
                return $this->children[$s[$index]]->getCountForSymbols($s, $index + 1);
            } else {
                return 0;
            }
        } else {
            return $this->count;
        }
    }

    /**
     * Generates next string for given list of symbol and index
     *
     * @param array $s     list of symbol
     * @param int $index index index of generated string
     * @return ?string generated string.
     */
    public function generateNextString(array $s, int $index): ?string{
        $sum = 0.0;
        if ($index == count($s)){
            $prob = mt_rand() / mt_getrandmax();
            foreach ($this->children as $child){
                if ($prob < $child->probability + $sum){
                    return $child->symbol;
                } else {
                    $sum += $child->probability;
                }
            }
        } else {
            return $this->children[$s[$index]]->generateNextString($s, $index + 1);
        }
        return null;
    }

    /**
     * Prunes the NGramNode according to the given threshold. Removes the child(ren) whose probability is less than the
     * threshold.
     * @param float $threshold Threshold for pruning the NGram tree.
     * @param int $N N in N-Gram.
     */
    public function prune(float $threshold, int $N): void{
        if ($N == 0){
            $maxElement = null;
            $maxNode = null;
            $toBeDeleted = [];
            foreach ($this->children as $symbol => $value){
                if ($value->count / ($this->count + 0.0) < $threshold){
                    $toBeDeleted[] = $symbol;
                }
                if ($maxElement == null || $value->count > $this->children[$maxElement]->count){
                    $maxElement = $symbol;
                    $maxNode = $value;
                }
            }
            foreach ($toBeDeleted as $symbol){
                unset($this->children[$symbol]);
            }
            if (count($this->children) == 0){
                $this->children[$maxElement] = $maxNode;
            }
        } else {
            foreach ($this->children as $child){
                $child->prune($threshold, $N - 1);
            }
        }
    }
}