<?php

namespace DenielWorld\cache;

class DeclaredVariablesCache{

    private static array $vars = [];

    public static function declareVariable(string $var){
        self::$vars[] = $var;
    }

    public static function isDeclared(string $var) : bool{
        return in_array($var, self::$vars);
    }

}