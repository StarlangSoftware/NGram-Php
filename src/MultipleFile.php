<?php

namespace olcaytaner\NGram;

use olcaytaner\Util\FileContents;

class MultipleFile
{
    private int $fileIndex;
    private array $fileNameList;
    private FileContents $contents;

    /**
     * Constructor for {@link MultipleFile} class. Initializes the buffer reader with the first input file
     * from the fileNameList. MultipleFile supports simple multipart file system, where a text file is divided
     * into multiple files.
     * @param ...$args A list of files given as dynamic parameters.
     */
    public function __construct(array $args){
        $this->fileIndex = 0;
        $this->fileNameList = $args;
        $this->contents = new FileContents($args[$this->fileIndex]);
    }

    /**
     * Reads a single line from the current file. If the end of file is reached for the current file,
     * next file is opened and a single line from that file is read. If all files are read, the method
     * returns null.
     * @return string Read line from the current file.
     */
    public function readLine(): string{
        if (!$this->contents->hasNextLine()){
            $this->fileIndex++;
            $this->contents = new FileContents($this->fileNameList[$this->fileIndex]);
        }
        return $this->contents->readLine();
    }

    /**
     * Checks if the current file has more lines to be read.
     * @returns bool True if the current file has more lines to be read, false otherwise.
     */
    public function hasNextLine(): bool{
        return $this->fileIndex != count($this->fileNameList) - 1 || $this->contents->hasNextLine();
    }

    public function readCorpus(): array{
        $corpus = array();
        while ($this->hasNextLine()){
            $words = explode(" ", trim($this->readLine()));
            $corpus[] = $words;
        }
        return $corpus;
    }
}