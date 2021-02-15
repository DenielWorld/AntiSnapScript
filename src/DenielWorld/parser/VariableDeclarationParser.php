<?php

namespace DenielWorld\parser;

use DenielWorld\cache\DeclaredVariablesCache;
use DenielWorld\Loader;
use DenielWorld\xml\ProjectManager;

class VariableDeclarationParser extends Parser
{

    private const SCRIPT = 0, PUBLIC = 1, PRIVATE = 2;

    private bool $stage;

    private bool $insideListener;

    private ?ExecutableScriptParser $parentParser;

    private int $type = self::SCRIPT;

    private array $varNames = [];

    private array $varValues = [];

    private array $scriptVars = [];

    private ?string $file;

    private static int $listId = 1;

    public function __construct(?string $codeSequence = null, bool $stage = true, bool $insideListener = false, ?ExecutableScriptParser $parentParser = null, ?string $file = null)
    {
        parent::__construct($codeSequence);
        $this->stage = $stage;
        $this->insideListener = $insideListener;
        $this->parentParser = $parentParser;
        if (!$stage && $file === null)
            throw new \LogicException("Non-stage variable accessor must have a file name!" . Loader::getBrokenLineFormat());
        $this->file = $file;
    }

    public function getDeclaredScriptVars(): array
    {
        return $this->scriptVars;
    }

    public function parseErrors(): void
    {
        if (is_int(strpos($this->getCodeSequence(), "public"))) {
            $this->overrideCodeSequence(str_replace("public", "", $this->getCodeSequence()));
            $this->type = self::PUBLIC;
        } elseif (is_int(strpos($this->getCodeSequence(), "private"))) {
            $this->overrideCodeSequence(str_replace("private", "", $this->getCodeSequence()));
            $this->type = self::PRIVATE;
        } //All other keywords are ignored and assumed to be SCRIPT, may cause errors at this time.

        $setterExplode = explode("=", $this->getCodeSequence());
        //Declared with default value of 0
        if (count($setterExplode) === 1) {
            $varsExplode = explode(",", $setterExplode[0]);
            foreach ($varsExplode as $var) {
                if ($this->type === self::PUBLIC || $this->type === self::PRIVATE) {
                    if (DeclaredVariablesCache::isDeclared($var)) {
                        throw new \InvalidArgumentException("You cannot redeclare an already declared non-script variable." . Loader::getBrokenLineFormat());
                    }
                }
            }
            $this->varNames = $varsExplode;
            $this->varValues = [];
        } elseif (count($setterExplode) > 2 && is_bool(strpos($this->getCodeSequence(), "=="))) {
            throw new \LogicException("You cannot have multiple setter operators (=) in a single variable declaration statement." . Loader::getBrokenLineFormat());
        } else {
            $varNames = explode(",", $setterExplode[0]);
            foreach ($varNames as $var) {
                if ($this->type === self::PUBLIC || $this->type === self::PRIVATE) {
                    if (DeclaredVariablesCache::isDeclared($var)) {
                        if (!$this->insideListener) {
                            throw new \InvalidArgumentException("You cannot redeclare an already declared non-script variable." . Loader::getBrokenLineFormat());
                        } /*else {
                            //Setting the public/private variable from script scope
                        }*/
                    }
                }
            }
            if (substr($setterExplode[1], strlen($setterExplode[1]) - 1, 1) === "]" || is_int(strpos($setterExplode[1], '"],'))) {
                $varValues = explode("],", $setterExplode[1]);
                foreach ($varValues as $key => $value) {
                    if (substr($value, strlen($value) - 1, 1) !== "]" && substr($value, 0, 1) === "[") {
                        $varValues[$key] = $value . "]";
                    }
                }
            } else {
                $varValues = explode(",", $setterExplode[1]);
            }
            if (count($varNames) !== count($varValues)) {
                throw new \LogicException("When declaring one or more variables with defaults, default values must be provided for each variable." . Loader::getBrokenLineFormat());
            }
            $this->varNames = $varNames;
            $this->varValues = $varValues;
        }
    }

    public function output(): string
    {
        $ret = parent::output();
        $this->type = self::SCRIPT;
        return $ret;
    }

    public function decodeVariableData(string $data, bool $global = true): string
    {
        if (substr($data, 0, 1) === "[" && substr($data, strlen($data) - 1, 1) === "]") {
            $data = str_replace("[", "", $data);
            $data = str_replace("]", "", $data);
            if ($global) {
                $listId = ++self::$listId;
                return str_replace("'", '"', "<list id='$listId' struct='atomic'>$data</list>");
            } else {
                $expl = explode(",", $data);
                $listData = "";
                foreach ($expl as $listPiece) {
                    $listPiece = str_replace('"', "", $listPiece);
                    $listData .= "<l>$listPiece</l>";
                }
                return str_replace("'", '"', "<block s='reportNewList'> <list>$listData</list> </block>");
            }
        } elseif ($data === "true" || $data === "false") {
            if ($global) {
                return "<bool>$data</bool>";
            } else {
                return str_replace("'", '"',
                    "<block s='reportBoolean'>
                                <l>
                                    <bool>$data</bool>
                                </l>
                        </block>");
            }
        } elseif (in_array($data, $this->scriptVars) || DeclaredVariablesCache::isDeclared($data)) {
            if (!$global) {
                return str_replace("'", '"', "<block var='$data'/>");
            } else {
                throw new \LogicException("A variable cannot be dependent on another variable on a global scope." . Loader::getBrokenLineFormat());
            }
        } elseif (is_int(strpos($data, "(")) && is_int(strpos($data, ")")) && $this->insideListener) {
            return ExecutableScriptParser::parseExecutableStatement($data, $this->getDeclaredScriptVars(), $this->parentParser);
        } elseif (is_int(strpos($this->getCodeSequence(), ">")) || is_int(strpos($this->getCodeSequence(), "==")) || is_int(strpos($this->getCodeSequence(), "<"))) {
            if (is_int(strpos($this->getCodeSequence(), "=="))) $data = substr($this->getCodeSequence(), strpos($this->getCodeSequence(), "=") + 1);
            return ExecutableScriptParser::evaluateOperators($data, $this->getDeclaredScriptVars(), $this->parentParser);
        }

        $data = str_replace('"', "", $data);
        return "<l>$data</l>";
    }

    public function translate(): string
    {
        $xmls = [];
        if (count($this->varValues) > 0) {
            foreach ($this->varNames as $key => $var) {
                $value = $this->decodeVariableData($this->varValues[$key], !($this->type === self::SCRIPT));
                $encodedXml =
                    "<variable name='$var'>
                                $value
                             </variable>";
                $encodedXml = str_replace("'", '"', $encodedXml);
                $xmls[] = $encodedXml;
                DeclaredVariablesCache::declareVariable($var);
            }
        } else {
            foreach ($this->varNames as $var) {
                $encodedXml =
                    "<variable name='$var'>
                                <l>0</l>
                             </variable>";
                $encodedXml = str_replace("'", '"', $encodedXml);
                $xmls[] = $encodedXml;
                DeclaredVariablesCache::declareVariable($var);
            }
        }

        if ($this->type === self::PUBLIC && !$this->insideListener) {
            foreach ($xmls as $encodedXml) {
                ProjectManager::putPublicVariable($encodedXml);
            }
        } elseif ($this->type === self::PRIVATE && !$this->insideListener) {
            foreach ($xmls as $encodedXml) {
                if ($this->stage)
                    ProjectManager::putPrivateStageVariable($encodedXml);
                else
                    ProjectManager::putPrivateSpriteVariable($this->file, $encodedXml);
            }
        } else {
            $varDeclarators = "";
            $varSetters = [];
            foreach ($this->varNames as $key => $var) {
                $value = $this->decodeVariableData($this->varValues[$key], false);

                if (!in_array($var, $this->scriptVars)) {
                    if ($this->type === self::SCRIPT && $this->insideListener)
                        $varDeclarators .= "<l>$var</l>";
                    $this->scriptVars[] = $var;
                }
                $varSetters[] = "<l>$var</l> $value";
            }

            if (strlen($varDeclarators) > 0) {
                //Declaration block should exist
                $declare =
                    "<block s='doDeclareVariables'>
                                    <list>
                                        $varDeclarators
                                    </list>
                         </block>";
                $declare = str_replace("'", '"', $declare);
            }
            $setterBlocks = "";
            foreach ($varSetters as $setterArgs) {
                $setterBlock = "<block s='doSetVar'>
                                        $setterArgs
                                    </block>";
                $setterBlock = str_replace("'", '"', $setterBlock);
                $setterBlocks .= $setterBlock . "
                    ";
            }

            return isset($declare) ? $declare . $setterBlocks : $setterBlocks;
        }
        $this->varNames = [];
        $this->varValues = [];

        return "undefined";
    }

}