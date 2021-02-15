<?php

namespace DenielWorld\xml;

class ProjectManager
{

    private static ?string $projectDir = null;

    private static ?string $projectContents = null;

    private static array $spriteNameCache = [];

    private const SPRITE_SKELETON = "<sprite NAME_SETTINGS_PLACEHOLDER>
                <costumes>
                    <list struct='atomic' id='9'></list>
                </costumes>
                <sounds>
                    <list struct='atomic' id='10'></list>
                </sounds>
                <blocks></blocks>
                <variables>
                    NAME_VARS_PLACEHOLDER
                </variables>
                <scripts>
                    NAME_SCRIPTS_PLACEHOLDER
                </scripts>
            </sprite>";

    private const DEFAULT_STAGE_DATA = [
        "name" => "Stage", "width" => "480", "height" => "360", "costume" => "0", "color" => "255,255,255,1",
        "tempo" => "60", "threadsafe" => "false", "penlog" => "false", "volume" => "100", "pan" => "0",
        "lines" => "round", "ternary" => "false", "hyperops" => "true", "codify" => "false",
        "inheritance" => "true", "sublistIDs" => "false", "scheduled" => "false", "id" => "1"
    ];

    private static array $DEFAULT_SPRITE_KEYS = [
        "name" => "Sprite", "idx" => "1", "x" => "0", "y" => "0", "heading" => "90", "scale" => "1",
        "volume" => "100", "pan" => "0", "rotation" => "1", "draggable" => "true", "costume" => "0",
        "color" => "80,80,80,1", "pen" => "tip", "id" => "8"
    ];

    public static function init(): void
    {
        $dest = realpath(getcwd()) . DIRECTORY_SEPARATOR . "xml" . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "project.xml";
        if (is_file($dest)) {
            unlink($dest);
        }
        copy(
            realpath(getcwd()) . DIRECTORY_SEPARATOR . "xml" . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "projectStructure.xml",
            $dest
        );
        self::$projectDir = $dest;
        self::$projectContents = file_get_contents($dest);
    }

    /**
     * @param array<string, string> $data
     */
    public static function putStageData(array $data): void
    {
        $stage_data = "";
        foreach (self::DEFAULT_STAGE_DATA as $key => $value) {
            if (!isset($data[$key]))
                $stage_data .= str_replace("'", '"', "$key='$value' ");
            else
                $stage_data .= str_replace("'", '"', "$key='$data[$key]' ");
        }
        self::$projectContents = str_replace("STAGE_DATA_PLACEHOLDER", str_replace('""', '"', $stage_data), self::$projectContents);
    }

    public static function putPublicVariable(string $data): void
    {
        self::$projectContents = str_replace("PUBLIC_VARS_PLACEHOLDER", $data . "
        PUBLIC_VARS_PLACEHOLDER", self::$projectContents);
    }

    public static function putPrivateStageVariable(string $data): void
    {
        self::$projectContents = str_replace("STAGE_VARS_PLACEHOLDER_PRIVATE", $data . "
        STAGE_VARS_PLACEHOLDER_PRIVATE", self::$projectContents);
    }

    public static function putPrivateSpriteVariable(string $spriteName, string $data){
        self::$projectContents = str_replace("$spriteName" . "_VARS_PLACEHOLDER", $data . "
        $spriteName" . "_VARS_PLACEHOLDER", self::$projectContents);
    }

    public static function putStageScript(string $data): void
    {
        self::$projectContents = str_replace("STAGE_SCRIPTS_PLACEHOLDER", $data . "
        STAGE_SCRIPTS_PLACEHOLDER", self::$projectContents);
    }

    public static function createSpriteSkeleton(string $name)
    {
        $skeleton = str_replace("'", '"', self::SPRITE_SKELETON);
        $skeleton = str_replace("NAME_", $name . "_", $skeleton);
        self::$projectContents = str_replace("SPRITES_PLACEHOLDER", $skeleton . "
        SPRITES_PLACEHOLDER", self::$projectContents);
        self::$spriteNameCache[] = $name;
    }

    public static function putSpriteData(string $spriteName, array $data): void
    {
        $stage_data = "";
        foreach (self::$DEFAULT_SPRITE_KEYS as $key => $value) {
            if (!isset($data[$key]))
                $stage_data .= str_replace("'", '"', "$key='$value' ");
            else
                $stage_data .= str_replace("'", '"', "$key='$data[$key]' ");
        }
        self::$DEFAULT_SPRITE_KEYS["idx"]++; self::$DEFAULT_SPRITE_KEYS["id"]++;
        self::$projectContents = str_replace("$spriteName" . "_SETTINGS_PLACEHOLDER", str_replace('""', '"', $stage_data), self::$projectContents);
    }

    public static function putSpriteScript(string $spriteName, string $data): void
    {
        //-999999999 is a constant for a NULL PLACEHOLDER VALUE.
        $data = str_replace("<l>-999999999</l>", "", $data);
        self::$projectContents = str_replace("$spriteName" . "_SCRIPTS_PLACEHOLDER", $data . "
        $spriteName" . "_SCRIPTS_PLACEHOLDER", self::$projectContents);
    }

    public static function writeDataToFile(): void
    {
        self::clearLeftoverPlaceholders();
        $dir = str_replace(str_replace("/", substr("\delimeter", 0, 1), "src\DenielWorld\xml/resources"), "resources", self::$projectDir);
        file_put_contents($dir, self::$projectContents);
    }

    private static function clearLeftoverPlaceholders(): void
    {
        self::$projectContents = str_replace("PUBLIC_VARS_PLACEHOLDER", "", self::$projectContents);
        self::$projectContents = str_replace("STAGE_VARS_PLACEHOLDER_PRIVATE", "", self::$projectContents);
        self::$projectContents = str_replace("VAR_DEBUG_PLACEHOLDER", "", self::$projectContents);
        self::$projectContents = str_replace("STAGE_SCRIPTS_PLACEHOLDER", "", self::$projectContents);
        self::$projectContents = str_replace("SPRITES_PLACEHOLDER", "", self::$projectContents);
        self::$projectContents = str_replace("undefined", "", self::$projectContents);

        $remove = [];
        foreach (self::$spriteNameCache as $name){
            $remove[] = "$name" . "_SETTINGS_PLACEHOLDER";
            $remove[] = "$name" . "_VARS_PLACEHOLDER";
            $remove[] = "$name" . "_SCRIPTS_PLACEHOLDER";
        }

        self::$projectContents = str_replace($remove, "", self::$projectContents);
    }
}