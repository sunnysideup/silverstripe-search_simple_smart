<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Security\Permission;
use Sunnysideup\SearchSimpleSmart\Api\ExportKeywordList;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineFullContent;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Config\Config;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;
use SilverStripe\Assets\Folder;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Connect\MySQLSchemaManager;
use SilverStripe\Core\Flushable;
use SilverStripe\Control\Director;
use SilverStripe\Security\Security;

/**
 *
 * getExtraData($componentName, $itemID) method on the ManyManyList to retrieve those extra fields values:
 *
 */

class SearchEngineKeyword extends DataObject implements Flushable
{
    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SearchEngineKeyword';

    public static function flush()
    {
        ExportKeywordList::export_keyword_list();
    }

    /*
     * @var string
     */
    private static $singular_name = "Keyword";
    public function i18n_singular_name()
    {
        return $this->Config()->get('singular_name');
    }

    /*
     * @var string
     */
    private static $plural_name = "Keywords";
    public function i18n_plural_name()
    {
        return $this->Config()->get('plural_name');
    }

    /*
     * @var array
     */
    private static $db = array(
        "Keyword" => "Varchar(100)"
    );

    /*
     * @var array
     */
    private static $many_many = array(
        "SearchEngineDataObjects_Level1" => SearchEngineDataObject::class,
        //"SearchEngineDataObjects_Level3" => "SearchEngineDataObject"
    );

    /*
     * @var array
     */
    private static $belongs_many_many = array(
        "SearchEngineDataObjects_Level2" => SearchEngineDataObject::class,
        "SearchEngineSearchRecords" => SearchEngineSearchRecord::class,
        //"SearchEngineDataObjects_Level3" => "SearchEngineDataObject"
    );

    /*
     * @var array
     */
    private static $many_many_extraFields = array(
        "SearchEngineDataObjects_Level1" => array("Count" => "Int"),
        //"SearchEngineDataObjects_Level3" => array("Count" => "Int")
    );

    /*
     * @var array
     */
    private static $casting = array(
        "Title" => "Varchar"
    );

    /*
     * @var string
     */
    private static $default_sort = "\"Keyword\" ASC";

    /**
     * @var boolean
     */
    private static $remove_all_non_alpha_numeric = false;

    /*
     * @var array
     */
    private static $required_fields = array(
        "Keyword"
    );

    /*
     * @var array
     */
    private static $summary_fields = array(
        "Keyword" => "Keyword"
    );

    /*
     * @var array
     */
    private static $indexes = array(
        'SearchFields' => array(
            'type' => 'fulltext',
            'name' => 'SearchFields',
            'columns' => ['Keyword']
        )
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
     * @casted variable
     * @return string
     */
    public function getTitle()
    {
        if ($this->hasDatabaseField('Keyword')) {
            return $this->getField('Keyword');
        }
        return "#{$this->ID}";
    }

    private static $_keyword_cache = [];

    private static $_keyword_cache_request_count = [];

    /**
     * @param string $keyword
     *
     * @return SearchEngineKeyword
     */
    public static function add_keyword($keyword, $runClean = true)
    {
        if($runClean) {
            self::clean_keyword($keyword);
        }
        if(! isset(self::$_keyword_cache_request_count[$keyword])) {
            self::$_keyword_cache_request_count[$keyword] = 0;
        }
        self::$_keyword_cache_request_count[$keyword]++;
        if(! isset(self::$_keyword_cache[$keyword])) {
            $fieldArray = array("Keyword" => $keyword);
            $obj = DataObject::get_one(SearchEngineKeyword::class, $fieldArray);
            if (! $obj) {
                $obj = SearchEngineKeyword::create($fieldArray);
                $obj->write();
            }

            self::$_keyword_cache[$keyword] = $obj;
        }

        return self::$_keyword_cache[$keyword];
    }

    /*
     * @var array
     */
    private static $_clean_keyword_cache = [];

    /**
     * cleans a string
     * @param string $keyword
     * @return string
     * @todo: cache using SS caching system.
     */
    public static function clean_keyword($keyword)
    {
        if (! isset(self::$_clean_keyword_cache[$keyword])) {
            self::$_clean_keyword_cache[$keyword] = SearchEngineFullContent::clean_content($keyword);
        }

        return self::$_clean_keyword_cache[$keyword];
    }


    /**
     * level can be formatted as Level1, level1 or 1
     * @param int | string $level
     * @return int $level
     */
    public static function level_sanitizer($level)
    {
        $level = str_ireplace("level", "", $level);
        $level = intval($level);
        if (! in_array($level, [1,2,3])) {
            user_error("Level needs to be between 1 and 3", E_USER_WARNING);
            return 1;
        }
        return $level;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab("Root.Main", new ReadonlyField("Keyword", "Keyword"));
        return $fields;
    }
}
