<?php

namespace DenielWorld\parser;

use DenielWorld\Loader;
use DenielWorld\xml\ProjectManager;

class SpriteSettingsParser extends Parser{

    private bool $initializeSent = false;

    private bool $initializeEnded = false;

    private string $fileName;

    private const VALID_KEYS = ["name", "x", "y", "heading", "scale", "volume", "rotation", "draggable", "color", "pen"];

    /** @var array<string, string> */
    private array $data = [];

    private int $repeatedUseCase = 0;

    public function __construct(?string $codeSequence = null, ?string $fileName = null)
    {
        parent::__construct($codeSequence);
        if($fileName === null)
            throw new \LogicException("A sprite must have a unique name!");
        $this->fileName = $fileName;
    }

    public function parseErrors() : void{
        if(!$this->initializeSent){
            if($this->getCodeSequence() === "initialize("){
                $this->initializeSent = true;
            }
        } elseif(!$this->initializeEnded){
            if($this->getCodeSequence() === "initialize("){
                throw new \InvalidArgumentException("You cannot nest initialize statements!" . Loader::getBrokenLineFormat());
            } elseif($this->getCodeSequence() === ")"){
                $this->initializeEnded = true;
            }
        }
    }

    public function translate() : string{
        if($this->initializeSent && !$this->initializeEnded) {
            $cleanLine = $this->getCodeSequence();
            if($cleanLine !== "initialize(" && $cleanLine !== ")") {
                $segments = explode(":", $cleanLine);
                //This error log is not looking good here, how could I effectively move it to parseErrors?
                if (count($segments) > 2) {
                    throw new \InvalidArgumentException("Syntax error, a single setting can only have one value." . Loader::getBrokenLineFormat());
                } elseif (count($segments) < 2) {
                    throw new \InvalidArgumentException("Syntax error, a single setting must have a value." . Loader::getBrokenLineFormat());
                }
                $key = $segments[0];
                $value = $segments[1];
                if (in_array($key, self::VALID_KEYS)) {
                    $this->data[$key] = $this->formatSettingValue($value);
                } else {
                    throw new \InvalidArgumentException("Tried to set an unknown sprite setting." . Loader::getBrokenLineFormat());
                }
            }
        } else {
            $this->repeatedUseCase++;
            if($this->repeatedUseCase > 1)
                throw new \InvalidArgumentException("Your sprite initializer is either not started or not ended." . Loader::getBrokenLineFormat());
        }

        $this->attemptWriteSettings();

        return "undefined"; //Our translated line does not matter, we do not output a single parsed line.
    }

    private static function formatSettingValue(string $value){
        $ret = str_replace("[", "", $value);
        $ret = str_replace("]", "", $ret);
        if($ret[0] !== '"') $ret = '"' . $ret;
        if($ret[strlen($ret) - 1] !== '"') $ret .= '"';

        return $ret;
    }

    public function getData() : array{
        return $this->data;
    }

    public function isEnded() : bool{
        return $this->initializeEnded;
    }

    public function attemptWriteSettings() : void{
        if($this->isEnded()){
            ProjectManager::putSpriteData($this->fileName, $this->getData());
        }
    }

}