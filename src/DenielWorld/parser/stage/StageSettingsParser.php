<?php

namespace DenielWorld\parser\stage;

use DenielWorld\Loader;
use DenielWorld\parser\Parser;
use DenielWorld\xml\ProjectManager;

class StageSettingsParser extends Parser{

    private static bool $initializeSent = false;

    private static bool $initializeEnded = false;

    private const VALID_KEYS = ["name", "width", "height", "color", "tempo", "volume"];

    /** @var array<string, string> */
    private array $data = [];

    private static int $repeatedUseCase = 0;

    public function parseErrors() : void{
        if(!self::$initializeSent){
            if($this->getCodeSequence() === "initialize("){
                self::$initializeSent = true;
            }
        } elseif(!self::$initializeEnded){
            if($this->getCodeSequence() === "initialize("){
                throw new \InvalidArgumentException("You cannot nest initialize statements!" . Loader::getBrokenLineFormat());
            } elseif($this->getCodeSequence() === ")"){
                self::$initializeEnded = true;
            }
        }
    }

    public function translate() : string{
        if(self::$initializeSent && !self::$initializeEnded) {
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
                    $this->data[$key] = self::formatSettingValue($value);
                } else {
                    throw new \InvalidArgumentException("Tried to set an unknown stage setting." . Loader::getBrokenLineFormat());
                }
            }
        } else {
            self::$repeatedUseCase++;
            if(self::$repeatedUseCase > 1)
                throw new \InvalidArgumentException("Your stage initializer is either not started or not ended." . Loader::getBrokenLineFormat());
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

    public static function isEnded() : bool{
        return self::$initializeEnded;
    }

    public function attemptWriteSettings() : void{
        if(self::isEnded()){
            ProjectManager::putStageData($this->getData());
        }
    }

}