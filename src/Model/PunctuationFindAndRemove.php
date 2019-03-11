<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Core\Config\Config;
use Sunnysideup\SearchSimpleSmart\Model\SearchEnginePunctuationFindAndRemove;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;

class SearchEnginePunctuationFindAndRemove extends DataObject
{

    /**
     * @var string
     */
    private static $singular_name = "Punctuation to Remove";
    public function i18n_singular_name()
    {
        return self::$singular_name;
    }

    /**
     * @var string
     */
    private static $plural_name = "Punctuations to Remove";
    public function i18n_plural_name()
    {
        return self::$plural_name;
    }

    /**
     * @var array
     */
    private static $db = array(
        "Character" => "Varchar(3)",
        "Custom" => "Boolean(1)"
    );

    /**
     * @var array
     */
    private static $defaults = array(
        '\'s',
        "Custom" => 1
    );

    /**
     * @var array
     */
    private static $indexes = array(
        "Character" => true
    );

    /**
     * @var string
     */
    private static $default_sort = "\"Custom\" DESC, \"Character\" ASC";

    /**
     * @var array
     */
    private static $required_fields = array(
        "Character"
    );

    /**
     * @var array
     */
    private static $summary_fields = array(
        "Character" => "Character",
        "Custom.Nice" => "Manually Entered"
    );

    /**
     * @param strig $character
     * @return int
     */
    public static function is_listed($character)
    {
        return SearchEnginePunctuationFindAndRemove::get()
            ->filter(array("Character" => $character))->count();
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        if (Config::inst()->get(SearchEnginePunctuationFindAndRemove::class, "add_defaults") === true) {
            foreach ($defaults as $default) {
                if (!self::is_listed($default)) {
                    DB::alteration_message("Creating a punctuation: $default", "created");
                    $obj = SearchEnginePunctuationFindAndRemove::create();
                    $obj->Character = $default;
                    $obj->Custom = false;
                    $obj->write();
                }
            }
        }
    }
}
