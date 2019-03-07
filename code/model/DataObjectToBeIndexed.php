<?php
/**
 * presents a list of dataobjects
 * that need to be reindexed, because they have changed.
 *
 * Once they have been indexed, they will be removed again.
 *
 */

class SearchEngineDataObjectToBeIndexed extends DataObject
{

    /**
     * @var string
     */
    private static $singular_name = "To Be (re)Indexed";
    public function i18n_singular_name()
    {
        return self::$singular_name;
    }

    /**
     * @var string
     */
    private static $plural_name = "To Be (re)Indexed";
    public function i18n_plural_name()
    {
        return self::$plural_name;
    }

    /**
     * @var array
     */
    private static $db = array(
        "Completed" => "Boolean(1)",
    );

    /**
     * @var array
     */
    private static $has_one = array(
        "SearchEngineDataObject" => "SearchEngineDataObject",
    );

    /**
     * @var array
     */
    private static $required_fields = array(
        "SearchEngineDataObject"
    );

    /**
     * @var array
     */
    private static $field_labels = array(
        "SearchEngineDataObject" => "Searchable Object"
    );

    /**
     * @var array
     */
    private static $summary_fields = array(
        "Title" => "Searchable Object",
        "Completed.Nice" => "Completed"
    );

    /**
     * @var array
     */
    private static $casting = array(
        "Title" => "Varchar"
    );

    /**
     * @var array
     */
    private static $default_sort = array(
        "Completed" => "ASC",
        "Created" => "DESC"
    );

    /**
     * you must set this to true once you have your cron job
     * up and running.
     * The cron job runs this task every ?? minutes.
     * @var boolean
     */
    private static $cron_job_running = false;

    /**
     * @casted variable
     * @return string
     */
    public function getTitle()
    {
        if ($this->SearchEngineDataObjectID) {
            if ($obj = $this->SearchEngineDataObject()) {
                return $obj->getTitle();
            }
        }
        return "ERROR";
    }

    /**
     * @return bool
     */
    public function canDelete($member = null)
    {
        return true;
    }

    /**
     *
     * @param SearchEngineDataObject $item
     * @return SearchEngineDataObjectToBeIndexed
     */
    public static function add($item)
    {
        $fieldArray = array("SearchEngineDataObjectID" => $item->ID, "Completed" => 0);
        $objToBeIndexedRecord = SearchEngineDataObjectToBeIndexed::get()
            ->filter($fieldArray)->first();
        if ($objToBeIndexedRecord) {
            //do nothing
        } else {
            $objToBeIndexedRecord = SearchEngineDataObjectToBeIndexed::create($fieldArray);
            $objToBeIndexedRecord->write();
        }
        if (Config::inst()->get("SearchEngineDataObjectToBeIndexed", "cron_job_running")) {
            //cron will take care of it...
        } else {
            //do it immediately...
            $item->SourceObject()->searchEngineIndex();
            $objToBeIndexedRecord->Completed = 1;
            $objToBeIndexedRecord->write();
        }
        return $objToBeIndexedRecord;
    }

    /**
     * returns all the items that are more than five minutes old
     * @param bool
     * @return DataList
     */
    public static function to_run($upToNow = false)
    {
        $objects = SearchEngineDataObjectToBeIndexed::get()
            ->exclude(array("SearchEngineDataObjectID" => 0))
            ->filter(array("Completed" => 0));
        if ($upToNow) {
            //do nothing
        } else {
            $objects = $objects->where("UNIX_TIMESTAMP(\"Created\") < ".strtotime("5 minutes ago"));
        }
        return $objects;
    }
}
