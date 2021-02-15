<?php

namespace DenielWorld\parser;

use DenielWorld\Loader;

abstract class Parser {

    private ?string $codeSequence = null;

    private ?string $parsedCodeSequence = null;

    /**
     * Parser constructor.
     * @param string|null $codeSequence
     */
    public function __construct(?string $codeSequence = null){
        if($codeSequence !== null)
            $this->insert($codeSequence);
    }

    public function insert(string $codeSequence) : void{
        if($this->codeSequence !== null){
            throw new \InvalidArgumentException("There is already a code sequence present for translation!");
        }
        $this->codeSequence = $codeSequence;
    }

    public function output() : string{
        if($this->parsedCodeSequence !== null){
            $ret = $this->parsedCodeSequence;
            $this->parsedCodeSequence = null;

            return $ret;
        } else {
            throw new \InvalidArgumentException("There is no parsed code sequence to return!");
        }
    }

    public function parse() : void {
        if($this->codeSequence === null){
            throw new \InvalidArgumentException("There is no code sequence to parse!");
        }
        if($this->parsedCodeSequence !== null){
            throw new \InvalidArgumentException("There is already a parsed code sequence present!");
        }
        $this->parseErrors();
        $this->parsedCodeSequence = $this->translate();
        $this->codeSequence = null;
    }

    public function getCodeSequence() : ?string{
        return $this->codeSequence;
    }

    public function getParsedCodeSequence() : ?string{
        return $this->parsedCodeSequence;
    }

    protected function overrideCodeSequence(string $newSequence) : void{
        $this->codeSequence = $newSequence;
    }

    public static function removeSpaces(string $codeSequence, bool $init = false) : string{
        //While being more effective, unfortunately this did not satisfy requirements of our space removal.
        //$ret = str_replace(" ", "", $codeSequence);
        $ret = "";
        $len = strlen($codeSequence);
        $openString = false;
        for($i = 0; $i < $len; $i++){
            $item = $codeSequence[$i];
            if($item === '"') $openString = !$openString;
            if($item === " " && !$openString) continue;
            else $ret .= $item;
        }
        if($init) $ret = substr($ret, 0, strlen($ret) - 2);
        return $ret;
    }

    abstract public function parseErrors() : void;

    abstract public function translate() : string;

}