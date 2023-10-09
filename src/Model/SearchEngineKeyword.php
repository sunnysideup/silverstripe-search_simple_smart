<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Flushable;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\Connect\MySQLSchemaManager;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use Sunnysideup\SearchSimpleSmart\Api\ExportKeywordList;

/**
 * getExtraData($componentName, $itemID) method on the ManyManyList to retrieve those extra fields values:.
 */
class SearchEngineKeyword extends DataObject implements Flushable
{
    protected static $_keyword_cache = [];

    protected static $_keyword_cache_request_count = [];

    // @var array
    protected static $_clean_keyword_cache = [];

    /**
     * Defines the database table name.
     *
     * @var string
     */
    private static $table_name = 'SearchEngineKeyword';

    // @var string
    private static $singular_name = 'Keyword';

    // @var string
    private static $plural_name = 'Keywords';

    // @var array
    private static $db = [
        'Keyword' => 'Varchar(100)',
    ];

    private static $auto_replace_map = [
        'ā' => 'a', 'ē' => 'e', 'ī' => 'i', 'ō' => 'o', 'ū' => 'u',
        'Ā' => 'A', 'Ē' => 'E', 'Ī' => 'I', 'Ō' => 'O', 'Ū' => 'U',
    ];

    // @var array
    private static $many_many = [
        'SearchEngineDataObjects_Level1' => SearchEngineDataObject::class,
        //"SearchEngineDataObjects_Level3" => "SearchEngineDataObject"
    ];

    // @var array
    private static $belongs_many_many = [
        'SearchEngineDataObjects_Level2' => SearchEngineDataObject::class,
        'SearchEngineSearchRecords' => SearchEngineSearchRecord::class,
        //"SearchEngineDataObjects_Level3" => "SearchEngineDataObject"
    ];

    // @var array
    private static $many_many_extraFields = [
        'SearchEngineDataObjects_Level1' => ['Count' => 'Int'],
        //"SearchEngineDataObjects_Level3" => array("Count" => "Int")
    ];

    // @var array
    private static $casting = [
        'Title' => 'Varchar',
    ];

    // @var string
    private static $default_sort = '"Keyword" ASC';

    // @var array
    private static $required_fields = [
        'Keyword',
    ];

    // @var array
    private static $summary_fields = [
        'Keyword' => 'Keyword',
        'SearchEngineDataObjects_Level1.Count' => 'Level 1 Mentions',
        'SearchEngineDataObjects_Level2.Count' => 'Level 2 Mentions',
        'SearchEngineSearchRecords.Count' => 'Included In Results',
    ];

    // @var array
    private static $indexes = [
        'SearchFields' => [
            'type' => 'fulltext',
            'name' => 'SearchFields',
            'columns' => ['Keyword'],
        ],
    ];

    /**
     * this is very important to allow Mysql FullText Searches.
     *
     * @var array
     */
    private static $create_table_options = [
        MySQLSchemaManager::ID => 'ENGINE=MyISAM',
    ];

    public static function flush()
    {
        if (Security::database_is_ready() && ! Director::is_cli()) {
            ExportKeywordList::export_keyword_list();
        }
    }

    public function i18n_singular_name()
    {
        return $this->Config()->get('singular_name');
    }

    public function i18n_plural_name()
    {
        return $this->Config()->get('plural_name');
    }

    /**
     * @param Member $member
     * @param mixed  $context
     *
     * @return bool
     */
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    public function canEdit($member = null)
    {
        return false;
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    public function canDelete($member = null)
    {
        return parent::canDelete() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    public function canView($member = null)
    {
        return parent::canView() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @casted variable
     *
     * @return string
     */
    public function getTitle()
    {
        if ($this->hasDatabaseField('Keyword')) {
            return $this->getField('Keyword');
        }

        return "#{$this->ID}";
    }

    /**
     * @param string $keyword
     * @param mixed  $runClean
     *
     * @return SearchEngineKeyword
     */
    public static function add_keyword($keyword, $runClean = true)
    {
        if ($runClean) {
            self::clean_keyword($keyword);
        }

        if (! isset(self::$_keyword_cache_request_count[$keyword])) {
            self::$_keyword_cache_request_count[$keyword] = 0;
        }

        ++self::$_keyword_cache_request_count[$keyword];
        if (! isset(self::$_keyword_cache[$keyword])) {
            $fieldArray = ['Keyword' => $keyword];
            $obj = DataObject::get_one(self::class, $fieldArray);
            if (! $obj) {
                $obj = self::create($fieldArray);
                $obj->write();
            }

            self::$_keyword_cache[$keyword] = $obj;
        }
        self::add_auto_replace($keyword);

        return self::$_keyword_cache[$keyword];
    }

    protected static function add_auto_replace(string $keyword): void
    {
        $replaceMap = Config::inst()->get(self::class, 'auto_replace_map');
        $wordWithoutMacrons = str_replace(array_keys($replaceMap), array_values($replaceMap), $keyword);
        if($wordWithoutMacrons === $keyword) {
            return;
        }
        $filter = ['Keyword' => $keyword];
        $obj = SearchEngineKeywordFindAndReplace::get()->filter($filter)->first();
        if($obj) {
            $obj->ReplaceWith .= ', '.$wordWithoutMacrons;
        } else {
            $obj = SearchEngineKeywordFindAndReplace::create($filter);
            $obj->ReplaceWith = $wordWithoutMacrons;
        }
        $obj->Custom = false;
        $obj->write();
    }

    /**
     * cleans a string.
     *
     * @param string $keyword
     *
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
     * level can be formatted as Level1, level1 or 1.
     *
     * @param int|string $level
     *
     * @return int
     */
    public static function level_sanitizer($level)
    {
        $level = str_ireplace('level', '', $level);
        $level = (int) $level;
        if (! in_array($level, [1, 2, 3], true)) {
            user_error('Level needs to be between 1 and 3. It is ' . $level, E_USER_WARNING);

            return 1;
        }

        return $level;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab('Root.Main', new ReadonlyField('Keyword', 'Keyword'));

        return $fields;
    }
}
