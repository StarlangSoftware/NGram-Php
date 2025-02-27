<?php

use olcaytaner\NGram\MultipleFile;
use olcaytaner\NGram\NGram;

class NGramTest extends \PHPUnit\Framework\TestCase
{
    private array $text1 = ["<s>", "ali", "topu", "at", "mehmet", "ayşeye", "gitti", "</s>"];
    private array $text2 = ["<s>", "ali", "top", "at", "ayşe", "eve", "gitti", "</s>"];
    private array $text3 = ["<s>", "ayşe", "kitabı", "ver", "</s>"];
    private array $text4 = ["<s>", "ali", "topu", "mehmete", "at", "</s>"];
    private array $text5 = ["<s>", "ali", "topu", "at", "mehmet", "ayşeyle", "gitti", "</s>"];
    private array $simpleCorpus;

    public function setUp(): void
    {
        $this->simpleCorpus = [];
        $this->simpleCorpus[] = $this->text1;
        $this->simpleCorpus[] = $this->text2;
        $this->simpleCorpus[] = $this->text3;
        $this->simpleCorpus[] = $this->text4;
        $this->simpleCorpus[] = $this->text5;
    }

    public function testGetCountSimple()
    {
        $simpleUniGram = new NGram([$this->simpleCorpus], 1);
        $simpleBiGram = new NGram([$this->simpleCorpus], 2);
        $simpleTriGram = new NGram([$this->simpleCorpus], 3);
        $this->assertEquals(5, $simpleUniGram->getCount(["<s>"]));
        $this->assertEquals(0, $simpleUniGram->getCount(["mahmut"]));
        $this->assertEquals(1, $simpleUniGram->getCount(["kitabı"]));
        $this->assertEquals(4, $simpleBiGram->getCount(["<s>", "ali"]));
        $this->assertEquals(0, $simpleBiGram->getCount(["ayşe", "ali"]));
        $this->assertEquals(0, $simpleBiGram->getCount(["mahmut", "ali"]));
        $this->assertEquals(2, $simpleBiGram->getCount(["at", "mehmet"]));
        $this->assertEquals(1, $simpleTriGram->getCount(["<s>", "ali", "top"]));
        $this->assertEquals(0, $simpleTriGram->getCount(["ayşe", "kitabı", "at"]));
        $this->assertEquals(0, $simpleTriGram->getCount(["ayşe", "topu", "at"]));
        $this->assertEquals(0, $simpleTriGram->getCount(["mahmut", "evde", "kal"]));
        $this->assertEquals(2, $simpleTriGram->getCount(["ali", "topu", "at"]));
    }

    public function testGetCountComplex()
    {
        ini_set('memory_limit', '350M');
        $file = new MultipleFile(["../train.txt"]);
        $trainCorpus = $file->readCorpus();
        $complexUniGram = new NGram([$trainCorpus], 1);
        $this->assertEquals(20000, $complexUniGram->getCount(["<s>"]));
        $this->assertEquals(50, $complexUniGram->getCount(["atatürk"]));
        $complexBiGram = new NGram([$trainCorpus], 2);
        $this->assertEquals(11, $complexBiGram->getCount(["<s>", "mustafa"]));
        $this->assertEquals(3, $complexBiGram->getCount(["mustafa", "kemal"]));
        $complexTriGram = new NGram([$trainCorpus], 3);
        $this->assertEquals(1, $complexTriGram->getCount(["<s>", "mustafa", "kemal"]));
        $this->assertEquals(1, $complexTriGram->getCount(["mustafa", "kemal", "atatürk"]));
    }

    public function testVocabularySizeSimple()
    {
        $simpleUniGram = new NGram([$this->simpleCorpus], 1);
        $this->assertEquals(15, $simpleUniGram->vocabularySize());
    }

    public function testVocabularySizeComplex()
    {
        $file = new MultipleFile(["../train.txt"]);
        $trainCorpus = $file->readCorpus();
        $complexUniGram = new NGram([$trainCorpus], 1);
        $this->assertEquals(57625, $complexUniGram->vocabularySize());
        $file = new MultipleFile(["../test.txt"]);
        $testCorpus = $file->readCorpus();
        $complexUniGram = new NGram([$testCorpus], 1);
        $this->assertEquals(55485, $complexUniGram->vocabularySize());
        $file = new MultipleFile(["../validation.txt"]);
        $validationCorpus = $file->readCorpus();
        $complexUniGram = new NGram([$validationCorpus], 1);
        $this->assertEquals(35663, $complexUniGram->vocabularySize());
    }

    public function testPrune()
    {
        $simpleBiGram = new NGram([$this->simpleCorpus], 2);
        $simpleBiGram->prune(0.0);
        $this->assertEquals(4, $simpleBiGram->getCount(["<s>", "ali"]));
        $this->assertEquals(1, $simpleBiGram->getCount(["<s>", "ayşe"]));
        $this->assertEquals(3, $simpleBiGram->getCount(["ali", "topu"]));
        $this->assertEquals(1, $simpleBiGram->getCount(["ali", "top"]));
        $this->assertEquals(2, $simpleBiGram->getCount(["topu", "at"]));
        $this->assertEquals(1, $simpleBiGram->getCount(["topu", "mehmete"]));
        $simpleBiGram->prune(0.6);
        $this->assertEquals(4, $simpleBiGram->getCount(["<s>", "ali"]));
        $this->assertEquals(0, $simpleBiGram->getCount(["<s>", "ayşe"]));
        $this->assertEquals(3, $simpleBiGram->getCount(["ali", "topu"]));
        $this->assertEquals(0, $simpleBiGram->getCount(["ali", "top"]));
        $this->assertEquals(2, $simpleBiGram->getCount(["topu", "at"]));
        $this->assertEquals(0, $simpleBiGram->getCount(["topu", "mehmete"]));
        $simpleBiGram->prune(0.7);
        $this->assertEquals(4, $simpleBiGram->getCount(["<s>", "ali"]));
        $this->assertEquals(3, $simpleBiGram->getCount(["ali", "topu"]));
        $this->assertEquals(2, $simpleBiGram->getCount(["topu", "at"]));
        $simpleBiGram->prune(0.8);
        $this->assertEquals(4, $simpleBiGram->getCount(["<s>", "ali"]));
        $this->assertEquals(3, $simpleBiGram->getCount(["ali", "topu"]));
        $simpleBiGram->prune(0.9);
        $this->assertEquals(4, $simpleBiGram->getCount(["<s>", "ali"]));
    }

    public function testMerge()
    {
        $simpleUniGram = new NGram(["../simple1a.txt"]);
        $simpleUniGram->merge(new NGram(["../simple1b.txt"]));
        $this->assertEquals(18, $simpleUniGram->vocabularySize());
        $simpleBiGram = new NGram(["../simple2a.txt"]);
        $simpleBiGram->merge(new NGram(["../simple2b.txt"]));
        $simpleBiGram->merge(new NGram(["../simple2c.txt"]));
        $simpleBiGram->merge(new NGram(["../simple2d.txt"]));
        $this->assertEquals(21, $simpleBiGram->vocabularySize());
        $simpleTriGram = new NGram(["../simple3a.txt"]);
        $simpleTriGram->merge(new NGram(["../simple3b.txt"]));
        $simpleTriGram->merge(new NGram(["../simple3c.txt"]));
        $this->assertEquals(20, $simpleTriGram->vocabularySize());
    }

    public function testLoadMultiPart()
    {
        $simpleUniGram = new NGram(["../simple1part1.txt", "../simple1part2.txt"]);
        $simpleBiGram = new NGram(["../simple2part1.txt", "../simple2part2.txt", "../simple2part3.txt"]);
        $simpleTriGram = new NGram(["../simple3part1.txt", "../simple3part2.txt", "../simple3part3.txt", "../simple3part4.txt"]);
        $this->assertEquals(5, $simpleUniGram->getCount(["<s>"]));
        $this->assertEquals(0, $simpleUniGram->getCount(["mahmut"]));
        $this->assertEquals(1, $simpleUniGram->getCount(["kitabı"]));
        $this->assertEquals(4, $simpleBiGram->getCount(["<s>", "ali"]));
        $this->assertEquals(0, $simpleBiGram->getCount(["ayşe", "ali"]));
        $this->assertEquals(0, $simpleBiGram->getCount(["mahmut", "ali"]));
        $this->assertEquals(2, $simpleBiGram->getCount(["at", "mehmet"]));
        $this->assertEquals(1, $simpleTriGram->getCount(["<s>", "ali", "top"]));
        $this->assertEquals(0, $simpleTriGram->getCount(["ayşe", "kitabı", "at"]));
        $this->assertEquals(0, $simpleTriGram->getCount(["ayşe", "topu", "at"]));
        $this->assertEquals(0, $simpleTriGram->getCount(["mahmut", "evde", "kal"]));
        $this->assertEquals(2, $simpleTriGram->getCount(["ali", "topu", "at"]));
        $this->assertEquals(15, $simpleUniGram->vocabularySize());
    }

}