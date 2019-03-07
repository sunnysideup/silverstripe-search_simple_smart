<?php

/**
 * keyword replace engine
 *
 *
 */

class SearchEngineKeywordFindAndReplace extends DataObject
{

    /**
     * @var string
     */
    private static $singular_name = "Keyword Replace";
    public function i18n_singular_name()
    {
        return self::$singular_name;
    }

    /**
     * @var string
     */
    private static $plural_name = "Keywords Replace";
    public function i18n_plural_name()
    {
        return self::$plural_name;
    }

    /**
     * @var array
     */
    private static $db = array(
        "Keyword" => "Varchar(150)",
        "ReplaceWith" => "Text", // comma separated list ...
        "Custom" => "Boolean(1)"
    );

    /**
     * @var array
     */
    private static $indexes = array(
        "Keyword" => true
    );

    /**
     * @var string
     */
    private static $default_sort = "\"Custom\" DESC, \"Keyword\" ASC";

    /**
     * @var array
     */
    private static $defaults = array(
        "Custom" => 1
    );

    /**
     * @var array
     */
    private static $required_fields = array(
        "Keyword",
        "ReplaceWith"
    );

    /**
     * @var array
     */
    private static $summary_fields = array(
        "Keyword" => "Keyword",
        "ReplaceWith" => "Replace With",
        "Custom.Nice" => "Manually Entered"
    );

    /**
     * @var array
     */
    private static $field_labels = array(
        "Custom" => "Manually Entered"
    );

    /**
     * @return bool
     */
    public function canDelete($member = null)
    {
        return false;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $keywordField = $fields->fieldByName("Root.Main")->fieldByName('Keyword');
        $keywordField->setTitle("Original Keyword");
        $replaceWithField = $fields->fieldByName("Root.Main")->fieldByName('ReplaceWith');
        $replaceWithField->setRightTitle(
                "Enter all the keywords (separated by a comma) you need to be included when a user searches for the original keyword.<br>
                If you need the original keyword to be included in the search it should also be included <br>"
        );
        return $fields;
    }

    /**
     *
     * clean up entries
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Keyword = SearchEngineKeyword::clean_keyword($this->Keyword);
        $replaceWithArray = $this->multiExplode(array(",", " "), $this->ReplaceWith);
        $finalArray = array();
        foreach ($replaceWithArray as $key => $keyword) {
            $keyword = SearchEngineKeyword::clean_keyword($keyword);
            if (strlen($keyword) > 1) {
                $finalArray[] = $keyword;
            }
        }
        $this->ReplaceWith = implode(",", $finalArray);
    }

    /**
     * @param array $delimiters
     * @param string $string
     * @return array
     */
    private function multiExplode($delimiters, $string)
    {
        $ready = str_replace($delimiters, $delimiters[0], $string);
        $launch = explode($delimiters[0], $ready);
        return  $launch;
    }

    /**
     * making sure that we do not have infinite loops...
     * @var int
     */
    private static $_words_used = array();

    /**
     * this method is recursive...
     * @param string $keyword
     * @return string
     */
    public static function find_replacements($keyword)
    {
        $objects = SearchEngineKeywordFindAndReplace::get()
            ->filter(array("Keyword" => $keyword));
        $wordsUsed = array();
        self::$_words_used[$keyword] = $keyword;
        foreach ($objects as $object) {
            $newEntries = explode(",", $object->ReplaceWith);
            $newerEntries = array();
            foreach ($newEntries as $newEntryKeyword) {
                $newEntryKeyword = SearchEngineKeyword::clean_keyword($newEntryKeyword);
                if (!isset(self::$_words_used[$newEntryKeyword])) {
                    $newerEntries[] = SearchEngineKeywordFindAndReplace::find_replacements($newEntryKeyword);
                } else {
                    $newerEntries[] = $newEntryKeyword;
                }
            }
            return implode(" ", $newerEntries);
        }
        return $keyword;
    }
}
