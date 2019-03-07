<?php

/**
 *
 * getExtraData($componentName, $itemID) method on the ManyManyList to retrieve those extra fields values:
 *
 */

class SearchEngineKeyword extends DataObject implements Flushable
{
    public static function flush()
    {
        self::export_keyword_list();
    }
    /*
     * @var string
     */
    private static $singular_name = "Keyword";
    public function i18n_singular_name()
    {
        return self::$singular_name;
    }

    /*
     * @var string
     */
    private static $plural_name = "Keywords";
    public function i18n_plural_name()
    {
        return self::$plural_name;
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
        "SearchEngineDataObjects_Level1" => "SearchEngineDataObject",
        //"SearchEngineDataObjects_Level3" => "SearchEngineDataObject"
    );

    /*
     * @var array
     */
    private static $belongs_many_many = array(
        "SearchEngineDataObjects_Level2" => "SearchEngineDataObject",
        "SearchEngineSearchRecords" => "SearchEngineSearchRecord",
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
    private static $_add_keyword_cache = array();

    /*
     * @var array
     */
    private static $indexes = array(
        'SearchFields' => array(
            'type' => 'fulltext',
            'name' => 'SearchFields',
            'value' => '"Keyword"'
        )
    );

    /**
     * this is very important to allow Mysql FullText Searches
     * @var array
     */
    private static $create_table_options = array(
        'MySQLDatabase' => 'ENGINE=MyISAM'
    );


    /**
     * @return boolean
     */
    public function canDelete($member = null)
    {
        return false;
    }

    /**
     *
     * @var string
     */
    private static $keyword_list_folder_name = "_search_engine_keywords";

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

    public static function export_keyword_list()
    {
        $fileName = self::get_js_keyword_file_name(true);
        if ($fileName) {
            //only write once a minute
            if (file_exists($fileName) && (time() -  filemtime($fileName) < 120)) {
                return "no new file created as the current one is less than 120 seconds old.";
            //do nothing
            } else {
                $rows = DB::query("SELECT \"Keyword\" FROM \"SearchEngineKeyword\" ORDER BY \"Keyword\";");
                $array = array();
                foreach ($rows as $row) {
                    $array[] = str_replace('"', "", Convert::raw2js($row["Keyword"]));
                }
                $written = null;
                if ($fh = fopen($fileName, 'w')) {
                    $written = fwrite($fh, "SearchEngineInitFunctions.keywordList = [\"".implode("\",\"", $array)."\"];");
                    fclose($fh);
                }
                if (!$written) {
                    user_error("Could not write keyword list to $fileName", E_USER_NOTICE);
                }
                return "Writting: <br />".implode("<br />", $array);
            }
        } else {
            return "no file name specified";
        }
    }

    /**
     * returns the location of the keyword file...
     * @param Boolean $withoutBase
     * @return string
     */
    public static function get_js_keyword_file_name($includeBase = false)
    {
        $fileName = "keywords.js";
        $myFolderName = Config::inst()->get("SearchEngineKeyword", "keyword_list_folder_name");
        if (!$myFolderName) {
            return false;
        }
        $myFolder = Folder::find_or_make($myFolderName);
        if ($includeBase) {
            return $myFolder->getFullPath().$fileName;
        } else {
            return $myFolder->FileName.$fileName;
        }
    }

    /**
     * @param string $keyword
     *
     * @return SearchEngineKeyword
     */
    public static function add_keyword($keyword)
    {
        self::clean_keyword($keyword);
        if (isset(self::$_add_keyword_cache[$keyword])) {
            //do nothing
        } else {
            $fieldArray = array("Keyword" => $keyword);
            self::$_add_keyword_cache[$keyword] = SearchEngineKeyword::get()
                ->filter($fieldArray)
                ->first();
            if (!self::$_add_keyword_cache[$keyword]) {
                $obj = SearchEngineKeyword::create($fieldArray);
                $obj->write();
                self::$_add_keyword_cache[$keyword] = $obj;
            }
        }
        return self::$_add_keyword_cache[$keyword];
    }


    private static $_punctuation_objects = null;

    /*
     * @var array
     */
    private static $_clean_keyword_cache = array();

    /**
     * cleans a string
     * @param string $keyword
     * @return string
     * @todo: cache using SS caching system.
     */
    public static function clean_keyword($keyword)
    {
        if (isset(self::$_clean_keyword_cache[$keyword])) {
            //do nothing
        } else {
            if (!self::$_punctuation_objects) {
                self::$_punctuation_objects = SearchEnginePunctuationFindAndRemove::get();
            }
            foreach (self::$_punctuation_objects as $punctuationObject) {
                $keyword = str_replace(self::$_punctuation_objects->Character, "", $keyword);
            }
            if (Config::inst()->get("SearchEngineKeyword", "remove_all_non_alpha_numeric")) {
                $keyword = preg_replace("/[^a-zA-Z 0-9]+/", "", $keyword);
            } else {

                //$keyword = preg_replace("/\P{L}+/u", " ", $keyword);
            }
            self::$_clean_keyword_cache[$keyword] = trim(
                strtolower(
                    //see: http://stackoverflow.com/questions/5059996/php-preg-replace-with-unicode-chars
                    //see: http://stackoverflow.com/questions/11989482/how-to-replace-all-none-alphabetic-characters-in-php-with-utf-8-support
                    //remove all non letters with NO space....
                    preg_replace(
                        '/\P{L}+/u',
                        '',
                        strip_tags($keyword)
                    )
                )
            );
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
        if ($level != 1 && $level != 2 && $level != 3) {
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
