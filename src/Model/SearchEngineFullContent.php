<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Security\Permission;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use SilverStripe\Core\Config\Config;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineFullContent;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Connect\MySQLSchemaManager;

/**
 * Full Content for each dataobject, separated by level of importance.
 *
 * Adding the content here, will also add it to the Keywords.
 *
 * Todo: consider breaking it up in sentences.
 */

class SearchEngineFullContent extends DataObject
{
    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SearchEngineFullContent';

    /*
     * @var string
     */
    private static $singular_name = "Full Content";
    public function i18n_singular_name()
    {
        return $this->Config()->get('singular_name');
    }

    /*
     * @var string
     */
    private static $plural_name = "Full Contents";
    public function i18n_plural_name()
    {
        return $this->Config()->get('plural_name');
    }

    /*
     * @var array
     */
    private static $db = array(
        "Level" => "Int(1)",
        "Content" => "Varchar(3000)"
    );

    /*
     * @var array
     */
    private static $has_one = array(
        "SearchEngineDataObject" => SearchEngineDataObject::class
    );

    /*
     * @var array
     */
    private static $indexes = array(
        "Level" => true,
        'SearchFields' => array(
            'type' => 'fulltext',
            'name' => 'SearchFields',
            'columns' => ['Content']
        )
    );

    /*
     * @var string
     */
    private static $default_sort = "\"Level\" ASC, \"Content\" ASC";

    /*
     * @var array
     */
    private static $required_fields = array(
        "Level",
        "Content"
    );

    /*
     * @var array
     */
    private static $summary_fields = array(
        "SearchEngineDataObject.Title" => "Searchable Object",
        "Level" => "Level",
        "ShortContent" => "Content"
    );

    /*
     * @var array
     */
    private static $field_labels = array(
        "SearchEngineDataObject" => "Data Object"
    );

    /*
     * @var array
     */
    private static $casting = array(
        "ShortContent" => "Varchar"
    );


    /**
     * this is very important to allow Mysql FullText Searches
     * @var array
     */
    private static $create_table_options = array(
        MySQLSchemaManager::ID => 'ENGINE=MyISAM'
    );

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canEdit($member = null, $context = [])
    {
        return false;
    }

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canDelete($member = null, $context = [])
    {
        return parent::canDelete() && Permission::check("SEARCH_ENGINE_ADMIN");
    }

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canView($member = null, $context = [])
    {
        return parent::canView() && Permission::check("SEARCH_ENGINE_ADMIN");
    }

    /**
     * @var bool
     */
    private static $remove_all_non_alpha_numeric = false;

    /**
     *
     * @param SearchEngineDataObject
     * @param array
     *     1 => content
     *     2 => content
     *     3 => content
     *
     * You can specify up to three levels
     */
    public static function add_data_object_array($item, $fullAray)
    {
        foreach ($fullAray as $level => $content) {
            self::add_one($item, $level, $content);
        }
    }

    /**
     * @param SearchEngineDataObject $item
     * @param int $level
     * @param string $content
     * @return object
     */
    public static function add_one($item, $level, $content)
    {
        $level = SearchEngineKeyword::level_sanitizer($level);
        //you dont want to clean keywords now as this will remove all the spaces!
        //$content = SearchEngineKeyword::clean_keyword($content);
        $fieldArray = array("SearchEngineDataObjectID" => $item->ID, "Level" => $level);
        $obj = SearchEngineFullContent::get()
            ->filter($fieldArray)
            ->first();
        if (!$obj) {
            $obj = SearchEngineFullContent::create($fieldArray);
        }
        $obj->Content = $content;
        $obj->write();
    }

    /**
     * @casted variable
     * @return string
     */
    public function getShortContent()
    {
        return substr($this->Content, 0, 50);
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Level = SearchEngineKeyword::level_sanitizer($this->Level);
        $this->Content = strip_tags($this->Content);
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        $fullArray = [];
        $item = $this->SearchEngineDataObject();
        if ($item) {
            //todo: turn Content into Keywords
            //1. take full content.
            $content = $this->Content;
            //2. remove stuff that is not needed (e.g. strip_tags)
            $content = SearchEngineFullContent::clean_content($content);
            $keywords = explode(" ", $content);
            foreach ($keywords as $keyword) {
                $keyword = SearchEngineKeyword::clean_keyword($keyword);
                if (strlen($keyword) > 1) {
                    //check if it is a valid keyword.
                    if (SearchEngineKeywordFindAndRemove::is_listed($keyword)) {
                        continue;
                    }
                    $keywordObject = SearchEngineKeyword::add_keyword($keyword);
                    if (!isset($fullArray[$keywordObject->ID])) {
                        $fullArray[$keywordObject->ID] = array(
                            "Object" => $keywordObject,
                            "Count" => 0
                        );
                    }
                    $fullArray[$keywordObject->ID]["Count"]++;
                }
            }
            //remove All previous entries
            $this->Level = SearchEngineKeyword::level_sanitizer($this->Level);
            $methodName = "SearchEngineKeywords_Level".$this->Level;
            $item->$methodName()->removeAll();
            //add all keywords
            foreach ($fullArray as $keywordObjectID => $arrayItems) {
                $keywordObject = $arrayItems["Object"];
                $count = $arrayItems["Count"];
                $methodName = "SearchEngineDataObjects_Level".$this->Level;
                $keywordObject->$methodName()->add($item, array("Count" => $count));
            }
        }
    }

    /*
     *
     */
    private static $_punctuation_objects = null;


    /**
     * cleans a string
     * @param string $content
     * @return string
     * @todo: cache using SS caching system.
     */
    public static function clean_content($content)
    {
        if (!self::$_punctuation_objects) {
            self::$_punctuation_objects = SearchEnginePunctuationFindAndRemove::get();
        }
        foreach (self::$_punctuation_objects as $punctuationObject) {
            $content = str_replace(self::$_punctuation_objects->Character, " ", $content);
        }
        if (Config::inst()->get(SearchEngineFullContent::class, "remove_all_non_alpha_numeric") == true) {
            $content = preg_replace("/[^a-zA-Z 0-9]+/", " ", $content);
        }
        $content = trim(
            strtolower(
                //remove all white space with single space
                //see: http://stackoverflow.com/questions/5059996/php-preg-replace-with-unicode-chars
                //see: http://stackoverflow.com/questions/11989482/how-to-replace-all-none-alphabetic-characters-in-php-with-utf-8-support
                preg_replace(
                    '/\P{L}+/u',
                    ' ',
                    strip_tags($content)
                )
            )
        );
        return $content;
    }
}
