<?php

use olcaytaner\NGram\GoodTuringSmoothing;
use olcaytaner\NGram\LaplaceSmoothing;
use olcaytaner\NGram\NGram;
use olcaytaner\NGram\NoSmoothing;
use PHPUnit\Framework\TestCase;

class SimpleSmoothingTest extends TestCase
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

    public function testNoSmoothing()
    {
        $simpleSmoothing = new NoSmoothing();
        $simpleUniGram = new NGram([$this->simpleCorpus], 1);
        $simpleBiGram = new NGram([$this->simpleCorpus], 2);
        $simpleTriGram = new NGram([$this->simpleCorpus], 3);
        $simpleUniGram->calculateNGramProbabilitiesSimple($simpleSmoothing);
        $simpleBiGram->calculateNGramProbabilitiesSimple($simpleSmoothing);
        $simpleTriGram->calculateNGramProbabilitiesSimple($simpleSmoothing);
        $this->assertEquals(5 / 35.0, $simpleUniGram->getProbability("<s>"));
        $this->assertEquals(0.0, $simpleUniGram->getProbability("mahmut"));
        $this->assertEquals(1.0 / 35.0, $simpleUniGram->getProbability("kitabı"),);
        $this->assertEquals(4 / 5.0, $simpleBiGram->getProbability("<s>", "ali"));
        $this->assertEquals(0 / 2.0, $simpleBiGram->getProbability("ayşe", "ali"));
        $this->assertEquals(0.0, $simpleBiGram->getProbability("mahmut", "ali"));
        $this->assertEquals(2 / 4.0, $simpleBiGram->getProbability("at", "mehmet"));
        $this->assertEquals(1 / 4.0, $simpleTriGram->getProbability("<s>", "ali", "top"));
        $this->assertEquals(0 / 1.0, $simpleTriGram->getProbability("ayşe", "kitabı", "at"));
        $this->assertEquals(0.0, $simpleTriGram->getProbability("ayşe", "topu", "at"));
        $this->assertEquals(0.0, $simpleTriGram->getProbability("mahmut", "evde", "kal"));
        $this->assertEquals(2 / 3.0, $simpleTriGram->getProbability("ali", "topu", "at"));
    }

    public function testLaplaceSmoothing()
    {
        $simpleSmoothing = new LaplaceSmoothing();
        $simpleUniGram = new NGram([$this->simpleCorpus], 1);
        $simpleBiGram = new NGram([$this->simpleCorpus], 2);
        $simpleTriGram = new NGram([$this->simpleCorpus], 3);
        $simpleUniGram->calculateNGramProbabilitiesSimple($simpleSmoothing);
        $simpleBiGram->calculateNGramProbabilitiesSimple($simpleSmoothing);
        $simpleTriGram->calculateNGramProbabilitiesSimple($simpleSmoothing);
        $this->assertEquals((5 + 1) / (35 + $simpleUniGram->vocabularySize() + 1), $simpleUniGram->getProbability("<s>"), 0.0);
        $this->assertEquals((0 + 1) / (35 + $simpleUniGram->vocabularySize() + 1), $simpleUniGram->getProbability("mahmut"), 0.0);
        $this->assertEquals((1 + 1) / (35 + $simpleUniGram->vocabularySize() + 1), $simpleUniGram->getProbability("kitabı"), 0.0);
        $this->assertEquals((4 + 1) / (5 + $simpleBiGram->vocabularySize() + 1), $simpleBiGram->getProbability("<s>", "ali"), 0.0);
        $this->assertEquals((0 + 1) / (2 + $simpleBiGram->vocabularySize() + 1), $simpleBiGram->getProbability("ayşe", "ali"), 0.0);
        $this->assertEquals(1 / ($simpleBiGram->vocabularySize() + 1), $simpleBiGram->getProbability("mahmut", "ali"), 0.0);
        $this->assertEquals((2 + 1) / (4 + $simpleBiGram->vocabularySize() + 1), $simpleBiGram->getProbability("at", "mehmet"), 0.0);
        $this->assertEquals((1 + 1) / (4.0 + $simpleTriGram->vocabularySize() + 1), $simpleTriGram->getProbability("<s>", "ali", "top"), 0.0);
        $this->assertEquals((0 + 1) / (1.0 + $simpleTriGram->vocabularySize() + 1), $simpleTriGram->getProbability("ayşe", "kitabı", "at"), 0.0);
        $this->assertEquals(1 / ($simpleTriGram->vocabularySize() + 1), $simpleTriGram->getProbability("ayşe", "topu", "at"), 0.0);
        $this->assertEquals(1 / ($simpleTriGram->vocabularySize() + 1), $simpleTriGram->getProbability("mahmut", "evde", "kal"), 0.0);
        $this->assertEquals((2 + 1) / (3.0 + $simpleTriGram->vocabularySize() + 1), $simpleTriGram->getProbability("ali", "topu", "at"), 0.0);
    }

    public function testGoodTuringSmoothing()
    {
        $simpleSmoothing = new GoodTuringSmoothing();
        $simpleUniGram = new NGram([$this->simpleCorpus], 1);
        $simpleBiGram = new NGram([$this->simpleCorpus], 2);
        $simpleTriGram = new NGram([$this->simpleCorpus], 3);
        $simpleUniGram->calculateNGramProbabilitiesSimple($simpleSmoothing);
        $simpleBiGram->calculateNGramProbabilitiesSimple($simpleSmoothing);
        $simpleTriGram->calculateNGramProbabilitiesSimple($simpleSmoothing);
        $this->assertEqualsWithDelta(0.116607, $simpleUniGram->getProbability("<s>"), 0.0001);
        $this->assertEqualsWithDelta(0.149464, $simpleUniGram->getProbability("mahmut"), 0.0001);
        $this->assertEqualsWithDelta(0.026599, $simpleUniGram->getProbability("kitabı"), 0.0001);
        $this->assertEqualsWithDelta(0.492147, $simpleBiGram->getProbability("<s>", "ali"), 0.0001);
        $this->assertEqualsWithDelta(0.030523, $simpleBiGram->getProbability("ayşe", "ali"), 0.0001);
        $this->assertEqualsWithDelta(0.0625, $simpleBiGram->getProbability("mahmut", "ali"), 0.0001);
        $this->assertEqualsWithDelta(0.323281, $simpleBiGram->getProbability("at", "mehmet"), 0.0001);
        $this->assertEqualsWithDelta(0.049190, $simpleTriGram->getProbability("<s>", "ali", "top"), 0.0001);
        $this->assertEqualsWithDelta(0.043874, $simpleTriGram->getProbability("ayşe", "kitabı", "at"), 0.0001);
        $this->assertEqualsWithDelta(0.0625, $simpleTriGram->getProbability("ayşe", "topu", "at"), 0.0001);
        $this->assertEqualsWithDelta(0.0625, $simpleTriGram->getProbability("mahmut", "evde", "kal"), 0.0001);
        $this->assertEqualsWithDelta(0.261463, $simpleTriGram->getProbability("ali", "topu", "at"), 0.0001);
    }

}