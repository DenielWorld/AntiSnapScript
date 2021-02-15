<?php

namespace DenielWorld;

use DenielWorld\parser\ExecutableScriptParser;
use DenielWorld\parser\Parser;
use DenielWorld\parser\SpriteSettingsParser;
use DenielWorld\parser\stage\StageSettingsParser;
use DenielWorld\parser\VariableDeclarationParser;
use DenielWorld\xml\ProjectManager;

spl_autoload_register(function ($class_name) {
    $delimeter = substr("\delimeter", 0, 1);
    $expl = explode($delimeter, $class_name); unset($expl[0]);
    $class_name = $delimeter . implode($delimeter, $expl);
    include realpath(getcwd()) . $class_name . '.php';
});

ProjectManager::init();
new Loader();

class Loader{

    private static ?string $dir;

    private static int $currentLine = 0;

    private static string $currentFile = "";

    public function __construct()
    {
        self::$dir = str_replace("src\DenielWorld", "resources", realpath(getcwd()));
        $this->onLoad();
    }

    public function onLoad(){
        $dirContents = scandir(self::$dir);
        foreach ($dirContents as $potentialFile){
            $currentFile = null;
            if(substr($potentialFile, strlen($potentialFile) - 4, 4) === ".ass"){
                self::$currentFile = $potentialFile;
                $fileContents = file(self::$dir . DIRECTORY_SEPARATOR . $potentialFile);
                if($potentialFile === "stage.ass"){
                    $this->handleStage($fileContents);
                } else {
                    $this->handleSprite($fileContents, $potentialFile);
                }
            }
        }
        ProjectManager::writeDataToFile();
    }

    public function handleStage(array $fileContents) : void{
        $settingsParser = new StageSettingsParser();
        $varDeclarationParser = new VariableDeclarationParser();
        $fileContents = str_replace("forever", str_replace("'", '"', "forever(-999999999)"), $fileContents);
        self::$currentLine = 0;
        foreach ($fileContents as $line){
            self::$currentLine++;
            $initLine = Parser::removeSpaces($line, true);
            $normalLine = Parser::removeSpaces($line);
            $initLine = substr($initLine, 0, !is_bool(strpos($initLine, "//")) ? strpos($initLine, "//") : strlen($initLine));
            $normalLine = substr($normalLine, 0, !is_bool(strpos($normalLine, "//")) ? strpos($normalLine, "//") : strlen($normalLine));
            $openBracket = !is_bool(strpos($normalLine, "{"));
            if($normalLine === "" || substr($normalLine, 0, 2) === "//"){
                continue;//We skip empty lines and comment lines
            }
            if(!$settingsParser::isEnded()){
                $settingsParser->insert($initLine);
                $settingsParser->parse();
                $settingsParser->output();//The output goes into nothingness as the setting parsers are not per-line based returners.
            } else {
                if(is_int(strpos($normalLine, "var")) && !isset($scriptParser)){
                    $cleanLine = str_replace("var", "", $normalLine);
                    $varDeclarationParser->insert($cleanLine);
                    $varDeclarationParser->parse();
                    $varDeclarationParser->output();
                } elseif(substr($normalLine, 0, 7) === "listen." && $openBracket){
                    //substr($normalLine, strlen($normalLine) - 3, 1) === "{" (old bracket check)
                    $listenerName = str_replace("listen.", "", $initLine);
                    $listenerName = str_replace("{", "", $listenerName);
                    $argedListener = $listenerName;
                    $listenerName = explode("(", $listenerName)[0];
                    if(isset($scriptParser)){
                        throw new \LogicException("You cannot listen within a listener." . self::getBrokenLineFormat());
                    } elseif(in_array($listenerName, ExecutableScriptParser::SUPPORTED_LISTENERS)) {
                        $scriptParser = new ExecutableScriptParser($argedListener);
                        if(isset($x)) $scriptParser->passX($x);
                        $scriptParser->parse();
                        ProjectManager::putStageScript($scriptParser->output());
                    }
                } elseif(isset($scriptParser)){
                    //Insert the following line to parse
                    if($normalLine !== "}") $normalLine = substr($normalLine, 0, strlen($normalLine) - 2);
                    $scriptParser->insert($normalLine);
                    $scriptParser->parse();
                    $output = $scriptParser->output();
                    if($output !== "undefined") ProjectManager::putStageScript($output);

                    if($scriptParser->isClosed()) {
                        $x = $scriptParser->getX();
                        unset($scriptParser);
                    }
                }
            }
        }
    }

    private static function getCurrentLine() : int{
        return self::$currentLine;
    }

    private static function getCurrentFile() : string{
        return self::$currentFile;
    }

    public static function getBrokenLineFormat() : string{
        return " Error on line " . self::getCurrentLine() . " of " . self::getCurrentFile();
    }

    public function handleSprite(array $fileContents, string $filename) : void{
        $settingsParser = new SpriteSettingsParser(null, $filename);
        $varDeclarationParser = new VariableDeclarationParser(null, false, false, null, $filename);
        $fileContents = str_replace("forever", str_replace("'", '"', "forever(-999999999)"), $fileContents);
        ProjectManager::createSpriteSkeleton($filename);
        self::$currentLine = 0;
        foreach ($fileContents as $line){
            self::$currentLine++;
            $initLine = Parser::removeSpaces($line, true);
            $normalLine = Parser::removeSpaces($line);
            $initLine = substr($initLine, 0, !is_bool(strpos($initLine, "//")) ? strpos($initLine, "//") : strlen($initLine));
            $normalLine = substr($normalLine, 0, !is_bool(strpos($normalLine, "//")) ? strpos($normalLine, "//") : strlen($normalLine));
            $openBracket = !is_bool(strpos($normalLine, "{"));
            if($normalLine === "" || substr($normalLine, 0, 2) === "//"){
                continue;//We skip empty lines and comment lines
            }
            if(!$settingsParser->isEnded()){
                $settingsParser->insert($initLine);
                $settingsParser->parse();
                $settingsParser->output();//The output goes into nothingness as the setting parsers are not per-line based returners.
            } else {
                if(is_int(strpos($normalLine, "var")) && !isset($scriptParser)){
                    $cleanLine = str_replace("var", "", $normalLine);
                    $varDeclarationParser->insert($cleanLine);
                    $varDeclarationParser->parse();
                    $varDeclarationParser->output();
                } elseif(substr($normalLine, 0, 7) === "listen." && $openBracket){
                    //substr($normalLine, strlen($normalLine) - 3, 1) === "{" (old bracket check)
                    $listenerName = str_replace("listen.", "", $initLine);
                    $listenerName = str_replace("{", "", $listenerName);
                    $argedListener = $listenerName;
                    $listenerName = explode("(", $listenerName)[0];
                    if(isset($scriptParser)){
                        throw new \LogicException("You cannot listen within a listener." . self::getBrokenLineFormat());
                    } elseif(in_array($listenerName, ExecutableScriptParser::SUPPORTED_LISTENERS)) {
                        $scriptParser = new ExecutableScriptParser($argedListener, false, $filename);
                        if(isset($x)) $scriptParser->passX($x);
                        $scriptParser->parse();
                        ProjectManager::putSpriteScript($filename, $scriptParser->output());
                    }
                } elseif(isset($scriptParser)){
                    //Insert the following line to parse
                    if($normalLine !== "}") $normalLine = substr($normalLine, 0, strlen($normalLine) - 2);
                    $scriptParser->insert($normalLine);
                    $scriptParser->parse();
                    $output = $scriptParser->output();
                    if($output !== "undefined") ProjectManager::putSpriteScript($filename, $output);

                    if($scriptParser->isClosed()) {
                        $x = $scriptParser->getX();
                        unset($scriptParser);
                    }
                }
            }
        }
    }
}