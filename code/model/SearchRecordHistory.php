<?php

/**
 * the DataObject ClassName + ID is recorded separately
 * so that the log is not affected if the SearchEngineDataObject is deleted.
 *
 */

class SearchEngineSearchRecordHistory extends DataObject
{

    /**
     * @var string
     */
    private static $singular_name = "Search History";
    public function i18n_singular_name()
    {
        return self::$singular_name;
    }

    /**
     * @var string
     */
    private static $plural_name = "Search History";
    public function i18n_plural_name()
    {
        return self::$plural_name;
    }

    /**
     * @var array
     */
    private static $db = array(
        "Phrase" => "Varchar(150)",
        "DataObjectClassName" => "Varchar(150)",
        "DataObjectID" => "Int",
        "NumberOfResults" => "Int",
        "Session" => "Varchar(32)"
    );

    /**
     * @var array
     */
    private static $has_one = array(
        "SearchEngineSearchRecord" => "SearchEngineSearchRecord",
        "Member" => "Member"
    );

    /**
     * @var array
     */
    private static $summary_fields = array(
        "Created" => "Created",
        "Phrase" => "Phrase",
        "DataObjectClassName" => "Class",
        "DataObjectID" => "ID",
        "NumberOfResults" => "Result Count"
    );

    /**
     * @var array
     */
    private static $indexes = array(
        "Phrase" => true,
        "DataObjectClassName" => true,
        "DataObjectID" => true
    );

    /**
     *
     * add an entry SearchEngineSearchRecordHistory entry
     * @param SearchEngineSearchRecord
     */
    public static function add_entry($searchEngineSearchRecord)
    {
        //a real request - lets start a new search record history ...
        $fieldArray = array(
            "SearchEngineSearchRecordID" => $searchEngineSearchRecord->ID,
            "MemberID" => intval(Member::currentUserID())-0,
            "Session" => session_id()
        );
        //update latest search
        $obj = self::get_latest_search();
        if ($obj) {
            foreach ($fieldArray as $field => $value) {
                $obj->$field = $value;
            }
        } else {
            $obj = SearchEngineSearchRecordHistory::get()->filter($fieldArray)->first();
            if (!$obj) {
                $obj = SearchEngineSearchRecordHistory::create($fieldArray);
            }
        }
        Session::set("SearchEngineSearchRecordHistoryID", $obj->write());
        return $obj;
    }

    /**
     * add an entry SearchEngineSearchRecordHistory entry
     *
     * @param int $count
     *
     * @return SearchEngineSearchRecordHistory | null
     */
    public static function add_number_of_results($count)
    {
        $obj = self::get_latest_search();
        if ($obj) {
            $obj->NumberOfResults = $count;
            $obj->write();
            return $obj;
        }
    }

    /**
     * Records what the user clicked on... from the search results.
     *
     * @param SearchEngineDataObject $item
     *
     * @return SearchEngineSearchRecordHistory | null

     */
    public static function register_click($item)
    {
        $obj = self::get_latest_search();
        if ($obj && ($item instanceof SearchEngineDataObject)) {
            $obj->DataObjectClassName = $item->DataObjectClassName;
            $obj->DataObjectID = $item->DataObjectID;
            $obj->write();
            return $obj;
        }
    }

    private static $_latest_search_cache = false;

    /**
     *
     * @return SearchEngineSearchRecordHistory | null
     */
    public static function get_latest_search()
    {
        if (self::$_latest_search_cache === false) {
            self::$_latest_search_cache = 0;
            $id = intval(Session::get("SearchEngineSearchRecordHistoryID"))-0;
            if ($id) {
                self::$_latest_search_cache = SearchEngineSearchRecordHistory::get()->byID($id);
            }
        }
        return self::$_latest_search_cache;
    }
}
