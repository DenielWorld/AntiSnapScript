<?php

namespace DenielWorld\parser;

use DenielWorld\cache\DeclaredVariablesCache;
use DenielWorld\Loader;

class ExecutableScriptParser extends Parser{

    public const SUPPORTED_LISTENERS = [
        "receiveGo", "receiveKey", "receiveInteraction", "receiveMessage", "receiveCondition"
    ];

    public const SUPPORTED_BLOCKS = [
        "forward", "turn", "turnLeft", "setHeading", "doFaceTowards", "gotoXY", "doGotoObject",
        "doGlide", "changeXPosition", "setXPosition", "changeYPosition", "setYPosition",
        "bounceOffEdge", "xPosition", "yPosition", "direction",
        "doSwitchToCostume", "doWearNextCostume", "getCostumeIdx", "reportGetImageAttribute",
        "reportNewCostume", "reportNewCostumeStretched", "doSayFor", "bubble", "doThinkFor",
        "doThink", "changeEffect", "setEffect", "getEffect", "clearEffects", "changeScale",
        "setScale", "getScale", "show", "hide", "reportShown", "goToLayer", "goBack",
        "doScreenshot", "reportCostumes", "alert", "log",
        "playSound", "doPlaySoundUntilDone", "doPlaySoundAtRate", "doStopAllSounds",
        "reportGetSoundAttribute", "reportNewSoundFromSamples", "doRest", "doPlayNote",
        "doPlayFrequency", "doSetInstrument", "doChangeTempo", "doSetTempo", "getTempo",
        "changeVolume", "setVolume", "getVolume", "changePan", "setPan", "getPan",
        "playFreq", "stopFreq", "reportSounds", "clear", "down", "up", "getPenDown",
        "setColor", "setPenHSVA", "changePenHSVA", "getPenAttribute", "setBackgroundColor",
        "setBackgroundHSVA", "changeBackgroundHSVA", "changeSize", "setSize", "doStamp",
        "floodFill", "write", "reportPenTrailsAsCostume", "reportPentrailsAsSVG", "doPasteFrom",
        "doCutFrom", "doBroadcast", "doBroadcastAndWait", "getLastMessage", "doSend", "doWait",
        "doWaitUntil", "doStopThis", "doRun", "fork", "evaluate", "doReport", "doCallCC",
        "reportCallCC", "doWarp", "doTellTo", "reportAskFor", "receiveOnClone", "createClone",
        "newClone", "removeClone", "doPauseAll", "reportTouchingObject", "reportTouchingColor",
        "reportColorIsTouchingColor", "reportAspect", "reportStackSize", "reportFrameCount",
        "reportYieldCount", "reportThreadCount", "doAsk", "reportLastAnswer", "getLastAnswer",
        "reportMouseX", "reportMouseY", "reportMouseDown", "reportKeyPressed", "reportRelationTo",
        "doResetTimer", "reportTimer", "getTimer", "reportAttributeOf", "reportObject", "reportURL",
        "doSetGlobalFlag", "reportGlobalFlag", "reportDate", "reportGet", "reportAudio", "reportMin",
        "reportMax", "reportRandom", "reportAnd", "reportOr", "reportNot", /*These three are here
        until they are integrated into the syntax */ "reportJoinWords", "reportLetter", "reportStringSize",
        "reportUnicode", "reportUnicodeAsLetter", "reportTextSplit", "reportJSFunction",
        "doShowVar", "doHideVar", "reportListItem", "reportListLength", "reportListContainsItem",
        "reportListIsEmpty", "reportListIndex", "doAddToList", "doDeleteFromList", "doInsertInList",
        "doReplaceInList",
        "doSetVideoTransparency", "reportVideo"
    ];

    public const SUPPORTED_NESTED_STATEMENTS = [
        "repeat" => "doRepeat", "if" => "doIf", "forever" => "doForever", "until" => "doUntil",
        "for" => "doFor", "foreach" => "doForEach"
    ];

    private array $closures = [];

    private bool $isClosed = false;

    private bool $firstRun = true;

    private int $openNestedScripts = 0;

    private bool $stage;

    private int $x = 0;

    private VariableDeclarationParser $varParser;

    public function __construct(string $codeSequence, bool $stage = true, ?string $file = null)
    {
        $this->stage = $stage;
        $this->varParser = new VariableDeclarationParser(null, $stage, true, $this, $file);
        parent::__construct($codeSequence);
    }

    public function isClosed() : bool{
        return $this->isClosed;
    }

    private static function removeErrorRedundant(string $codeSequence) : string{
        $len = strlen($codeSequence);
        $stringOpen = false;
        $new = "";
        for($i = 0; $i < $len; $i++){
            $sub = $codeSequence[$i];
            if(is_numeric($sub))
                continue;
            if($sub === '"'){
                $stringOpen = !$stringOpen;
                continue;
            } elseif(!$stringOpen && $sub !== ","){
                $new .= $sub;
            }
        }

        if($new[strlen($new) - 1] === "(") $new = substr($new, 0, strlen($new) - 2);
        return str_replace(")", "", $new);
    }

    private static function isSafe(string $codeSequence) : bool{
        $codeSequence = str_replace(["+", "-", "*", "/", "%", "^", " "], "", $codeSequence);

        return is_numeric($codeSequence);
    }

    private static function getOperatorBlock(string $codeSequence) : string{
        if(is_int(strpos($codeSequence, "+"))) return "reportSum";
        if(is_int(strpos($codeSequence, "-"))) return "reportDifference";
        if(is_int(strpos($codeSequence, "*"))) return "reportProduct";
        if(is_int(strpos($codeSequence, "/"))) return "reportQuotient";
        if(is_int(strpos($codeSequence, "%"))) return "reportModulus";
        if(is_int(strpos($codeSequence, "^"))) return "reportPower";
        return "undefined";
    }

    public static function isEvaluative(string $codeSequence) : bool{
        return
            is_int(strpos($codeSequence, ">")) ||
            is_int(strpos($codeSequence, "==")) ||
            is_int(strpos($codeSequence, "<")) ||
            self::getOperatorBlock($codeSequence) !== "undefined";
    }

    /**
     * @param string $codeSequence
     * @param array $scriptVars
     * @param ExecutableScriptParser|null $parser
     * @return string
     */
    public static function evaluateOperators(string $codeSequence, array $scriptVars = [], ?ExecutableScriptParser &$parser = null) : string{
        $codeSequences = explode(",", $codeSequence);
        $finalBlock = "";

        $nested = false;
        foreach (self::SUPPORTED_NESTED_STATEMENTS as $key => $value){
            if($codeSequence === $key){
                $nested = true;
                break;
            }
        }

        foreach ($codeSequences as $codeSequence) {
            $andExplode = explode("&&", $codeSequence);
            $orExplode = explode("||", $codeSequence);
            $greaterExplode = explode(">", $codeSequence);
            $smallerExplode = explode("<", $codeSequence);
            $equalExplode = explode("==", $codeSequence);

            if(in_array($codeSequence, self::SUPPORTED_BLOCKS)){
                $finalBlock .= str_replace("'", '"', "<block s='$codeSequence'>");
                $parser->closures[] = "</block>";
            } elseif (count($andExplode) > 1) {
                /*for($i = 0; $i < count($andExplode); $i++){
                    //Insert evaluated
                }
                //Close evaluated*/
                throw new \LogicException("This feature is not yet implemented!");
            } elseif (count($orExplode) > 1) {
                /*for($i = 0; $i < count($andExplode); $i++){
                    //Insert evaluated
                }
                //Close evaluated*/
                throw new \LogicException("This feature is not yet implemented!");
            } elseif (self::isSafe($codeSequence)) {//If clean integer evaluative
                $calculation = eval("return $codeSequence;");
                $finalBlock .= "<l>$calculation</l>";
            } elseif (count($greaterExplode) < 2 && count($smallerExplode) < 2 && count($equalExplode) < 2 && !$nested) {//If integer evaluative with variable
                $block = self::getOperatorBlock($codeSequence);
                if ($block === "undefined")
                    throw new \LogicException("Unknown operator in '$codeSequence'." . Loader::getBrokenLineFormat());

                $block = str_replace("'", '"', "<block s='$block'>");
                $codeSequence = str_replace(" ", "", $codeSequence);
                $multiExpl = self::multiExplode(["+", "-", "*", "/", "%", "^"], $codeSequence);
                if (count($multiExpl) > 2)
                    throw new \LogicException("Currently multiple numerical operations with variables on one line are not supported!" . Loader::getBrokenLineFormat());
                foreach ($multiExpl as $piece) {
                    if (is_numeric($piece)) $block .= "<l>$piece</l>";
                    elseif (DeclaredVariablesCache::isDeclared($piece) || in_array($piece, $scriptVars)) {
                        $block .= str_replace("'", '"', "<block var='$piece'/>");
                    } elseif(strlen($piece) > 0) {
                        throw new \LogicException("Undefined variable, $piece." . Loader::getBrokenLineFormat());
                    }
                }
                $block .= "</block>";

                $finalBlock .= $block;
            } elseif(count($greaterExplode) > 1){
                $finalBlock .= str_replace("'", '"', "<block s='reportGreaterThan'>");
                foreach ($greaterExplode as $part){
                    $part = str_replace(" ", "", $part);
                    if(DeclaredVariablesCache::isDeclared($part) || in_array($part, $parser->varParser->getDeclaredScriptVars())){
                        $finalBlock .= str_replace("'", '"', "<block var='$part'/>");
                    } else {
                        if(is_numeric($part)) {
                            $finalBlock .= "<l>$part</l>";
                        } else {
                            $finalBlock .= self::evaluateOperators($part, $scriptVars, $parser);
                        }
                    }
                }
                $finalBlock .= "</block>";
            } elseif(count($smallerExplode) > 1){
                $finalBlock .= str_replace("'", '"', "<block s='reportLessThan'>");
                foreach ($smallerExplode as $part){
                    $part = str_replace(" ", "", $part);
                    if(DeclaredVariablesCache::isDeclared($part) || in_array($part, $parser->varParser->getDeclaredScriptVars())){
                        $finalBlock .= str_replace("'", '"', "<block var='$part'/>");
                    } else {
                        if(is_numeric($part)) {
                            $finalBlock .= "<l>$part</l>";
                        } else {
                            $finalBlock .= self::evaluateOperators($part, $scriptVars, $parser);
                        }
                    }
                }
                $finalBlock .= "</block>";
            } elseif(count($equalExplode) > 1){
                $finalBlock .= str_replace("'", '"', "<block s='reportEquals'>");
                foreach ($equalExplode as $part){
                    $part = str_replace(" ", "", $part);
                    if(DeclaredVariablesCache::isDeclared($part) || in_array($part, $parser->varParser->getDeclaredScriptVars())){
                        $finalBlock .= str_replace("'", '"', "<block var='$part'/>");
                    } else {
                        if(is_numeric($part)) {
                            $finalBlock .= "<l>$part</l>";
                        } else {
                            $finalBlock .= self::evaluateOperators($part, $scriptVars, $parser);
                        }
                    }
                }
                $finalBlock .= "</block>";
            } else {
                throw new \LogicException("Invalid operation, $codeSequence." . Loader::getBrokenLineFormat());
            }
        }

        foreach (self::SUPPORTED_NESTED_STATEMENTS as $key => $_) {
            $cut = substr($parser->getCodeSequence(), 0, strpos($parser->getCodeSequence(), "("));
            if(is_int(strpos($cut, "{"))) substr($parser->getCodeSequence(), 0, strpos($parser->getCodeSequence(), "{"));
            if (is_int(strpos($cut, $key)) && !in_array($cut, self::SUPPORTED_BLOCKS)){
                foreach (self::SUPPORTED_BLOCKS as $block) {
                    if(is_int(strpos($cut, $block))){
                        break 2;
                    }
                }
                $finalBlock .= "<script>";
                $parser->openNestedScripts++;
                $parser->closures[] = "</script>";
                $parser->closures[] = "";
                break;
            }
        }

        return $finalBlock;
    }

    private static function multiExplode(array $delimiters, string $string) {
        $ready = str_replace($delimiters, $delimiters[0], $string);
        return explode($delimiters[0], $ready);
    }

    public static function parseExecutableStatement(string $codeSequence, array $declaredVars = [], ?ExecutableScriptParser &$parser = null) : string{
        $statement = "";
        $len = strlen($codeSequence);
        $ret = "";
        for($i = 0; $i < $len; $i++){
            $sub = $codeSequence[$i];
            if($sub !== "(" && $sub !== ")") {
                $statement .= $sub;
            } elseif($sub === ")"){
                if(strlen(str_replace(" ", "", $statement)) > 0){
                    $retAddon = "";
                    try {
                        $retAddon .= self::evaluateOperators($statement, $declaredVars, $parser);
                    } catch (\LogicException $e){
                        $vars = explode(",", str_replace(" ", "", $statement));
                        foreach ($vars as $var) {
                            if (DeclaredVariablesCache::isDeclared($var) || in_array($statement, $declaredVars)) {
                                $retAddon .= str_replace("'", '"', "<block var='$var'/>");
                            } else {
                                throw new \LogicException("Undefined variable $var." . Loader::getBrokenLineFormat());
                            }
                        }
                    }
                    $ret .= $retAddon;
                    $statement = "";
                }
                $ret .= array_pop($parser->closures);
            } else {
                if(in_array($statement, self::SUPPORTED_BLOCKS)){
                    $opening = "<block s='$statement'>";
                    $opening = str_replace("'", '"', $opening);
                    $ret .= $opening;
                    $parser->closures[] = "</block>";
                    $statement = "";
                } elseif(array_key_exists($statement, self::SUPPORTED_NESTED_STATEMENTS)) {
                    $blockName = self::SUPPORTED_NESTED_STATEMENTS[$statement];
                    $opening = str_replace("'", '"', "<block s='$blockName'>");
                    $ret .= $opening;
                    $parser->closures[] = "</block>";
                    $statement = "";
                } else {
                    $retAddon = "";
                    try {
                        $retAddon .= self::evaluateOperators($statement, $declaredVars, $parser);
                    } catch (\LogicException $e){
                        $vars = explode(",", str_replace(" ", "", $statement));
                        foreach ($vars as $var) {
                            if (DeclaredVariablesCache::isDeclared($var) || in_array($statement, $declaredVars)) {
                                $retAddon .= str_replace("'", '"', "<block var='$var'/>");
                            } else {
                                if(is_numeric($var)) $retAddon .= "<l>$var</l>";
                                //TODO Fix method calling in < > and ==
                                /*if(in_array($var, self::SUPPORTED_BLOCKS)){
                                    $retAddon .= str_replace("'", '"', "<block s='$var'>");
                                    $parser->closures[] = "</block>";
                                }*/ else throw new \LogicException("Undefined variable $var." . Loader::getBrokenLineFormat());
                            }
                        }
                    }
                    $ret .= $retAddon;
                    $statement = "";
                }
            }
        }

        if(strlen($statement) > 1){
            $statement = str_replace("{", "", $statement);
            if(array_key_exists($statement, self::SUPPORTED_NESTED_STATEMENTS)) {
                $blockName = self::SUPPORTED_NESTED_STATEMENTS[$statement];
                $opening = str_replace("'", '"', "<block s='$blockName'>");
                $ret .= $opening;
                $parser->closures[] = "</block>";
            }
        }

        return $ret;
    }

    public function parseErrors(): void
    {
        if(substr_count($this->getCodeSequence(), "(") !== substr_count($this->getCodeSequence(), ")")){
            throw new \LogicException("Each open bracket must have an according closing bracket on the same line." . Loader::getBrokenLineFormat());
        }
        if($this->isClosed()){
            throw new \LogicException("Attempted to utilize a closed script parser." . Loader::getBrokenLineFormat());
        }
        //if(is_bool(strpos($this->getCodeSequence(), "{")) && is_bool(strpos($this->getCodeSequence(), "}"))){
        if(is_bool(strpos($this->getCodeSequence(), "var")) && is_bool(strpos($this->getCodeSequence(), "}")) && is_bool(strpos($this->getCodeSequence(), "{"))){
            $str = self::removeErrorRedundant($this->getCodeSequence());
            $expl = explode("(", $str);
            foreach ($expl as $block){
                //TODO: Integrate support for custom blocks, switch to a non-const registry
                if(
                    !in_array($block, self::SUPPORTED_BLOCKS)
                    && !in_array($block, self::SUPPORTED_LISTENERS)
                    && !in_array($block, self::SUPPORTED_NESTED_STATEMENTS)
                    && $block !== "" && !DeclaredVariablesCache::isDeclared($block)
                    && !in_array($block, $this->varParser->getDeclaredScriptVars())
                    && strlen(self::evaluateOperators($block, $this->varParser->getDeclaredScriptVars(), $this)) === 0
                ){
                    throw new \InvalidArgumentException("Invalid block name given: $block." . Loader::getBrokenLineFormat());
                }
            }
        }
    }

    public function getX() : int{
        return $this->x;
    }

    public function passX(int $x) : void{
        $this->x = $x;
    }

    public function translate(): string
    {
        // } = double xml closing, ) = 1 closing
        if($this->firstRun && in_array(explode("(", $this->getCodeSequence())[0], self::SUPPORTED_LISTENERS)){
            $sequence = explode("(", $this->getCodeSequence())[0];
            $argument = "";
            foreach (explode("(", str_replace(")", "", $this->getCodeSequence())) as $key => $arg){
                if($key === 0) continue;
                $arg = str_replace('"', "", $arg);
                $argument = "<l> <option>$arg</option> </l>";
            }
            if($this->x === 0) $x = 35;
            else $x = $this->x + 180;
            $this->x = $x;
            $ret = "<script x='$x' y='31'>
                        <block s='$sequence'>$argument</block>";
            $this->closures[] = "</script>";
            return str_replace("'", '"', $ret);
        }
        if(is_int(strpos($this->getCodeSequence(), "var")) && !isset($scriptParser)){
            $cleanLine = str_replace("var", "", $this->getCodeSequence());
            $this->varParser->insert($cleanLine);
            $this->varParser->parse();
            //Variable declaration does not require any closure caches.
            return $this->varParser->output();
        }
        if($this->getCodeSequence() === "}"){
            //TODO: Account for ifse statements
            $ret = "";
            do {
                $ret .= array_pop($this->closures);
            } while (count($this->closures) > 0 && $this->closures[count($this->closures) - 1] !== "</block>");
            if($this->openNestedScripts > 0){
                $ret .= array_pop($this->closures);
                $this->openNestedScripts--;
            }
            return $ret;
        }
        if(is_int(strpos($this->getCodeSequence(), "(")) && is_int(strpos($this->getCodeSequence(), ")"))) {
            return self::parseExecutableStatement($this->getCodeSequence(), $this->varParser->getDeclaredScriptVars(), $this);
        }
        if(is_int(strpos($this->getCodeSequence(), "{"))){
            return self::parseExecutableStatement($this->getCodeSequence(), $this->varParser->getDeclaredScriptVars(), $this);
        }
        if(count($this->closures) === 0){
            $this->isClosed = true;
            return "undefined";
        }
        if($this->firstRun) $this->firstRun = false;
        return "undefined";
    }

}