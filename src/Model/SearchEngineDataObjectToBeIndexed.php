<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Security\Permission;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use SilverStripe\Core\Config\Config;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Forms\ReadonlyField;

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
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SearchEngineDataObjectToBeIndexed';

    /**
     * @var string
     */
    private static $singular_name = "To Be (re)Indexed";
    public function i18n_singular_name()
    {
        return $this->Config()->get('singular_name');
    }

    /**
     * @var string
     */
    private static $plural_name = "To Be (re)Indexed";
    public function i18n_plural_name()
    {
        return $this->Config()->get('plural_name');
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
    private static $indexes = array(
        "Completed" => true
    );

    /**
     * @var array
     */
    private static $has_one = array(
        "SearchEngineDataObject" => SearchEngineDataObject::class,
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
        "Created" => "Added On",
        "Completed.Nice" => "Completed"
    );

    /**
     * Defines a default list of filters for the search context
     * @var array
     */
    private static $searchable_fields = [
        'Completed' => 'ExactMatchFilter'
    ];

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

    private static $_cache_for_items = [];

    /**
     *
     * @param SearchEngineDataObject $item
     * @return SearchEngineDataObjectToBeIndexed
     */
    public static function add(SearchEngineDataObject $searchEngineDataObject, $alsoIndex = true)
    {
        if($searchEngineDataObject && $searchEngineDataObject->exists()) {
            if(! isset(self::$_cache_for_items[$searchEngineDataObject->ID])) {
                $fieldArray = [
                    "SearchEngineDataObjectID" => $searchEngineDataObject->ID,
                    "Completed" => 0
                ];
                $objToBeIndexedRecord = DataObject::get_one(
                    SearchEngineDataObjectToBeIndexed::class,
                    $fieldArray
                );
                if ($objToBeIndexedRecord && $objToBeIndexedRecord->exists()) {
                    //do nothing
                } else {
                    $objToBeIndexedRecord = SearchEngineDataObjectToBeIndexed::create($fieldArray);
                    $objToBeIndexedRecord->write();
                }
                if (Config::inst()->get(SearchEngineDataObjectToBeIndexed::class, "cron_job_running")) {
                    //cron will take care of it...
                } else {
                    //do it immediately...
                    if($alsoIndex) {
                        $objToBeIndexedRecord->IndexNow($searchEngineDataObject);
                    }
                }
                self::$_cache_for_items[$searchEngineDataObject->ID] = $objToBeIndexedRecord;
            }
            return self::$_cache_for_items[$searchEngineDataObject->ID];
        } else {
            user_error('The SearchEngineDataObject needs to exist');
        }
    }

    public function IndexNow(SearchEngineDataObject $searchEngineDataObject = null)
    {
        if(! $searchEngineDataObject) {
            $searchEngineDataObject  = $this->SearchEngineDataObject();
        }
        if($searchEngineDataObject && $searchEngineDataObject->exists() && $searchEngineDataObject instanceof SearchEngineDataObject) {
            $sourceObject = $searchEngineDataObject->SourceObject();
            if($sourceObject && $sourceObject->exists()) {
                $sourceObject->doSearchEngineIndex($searchEngineDataObject);
                $this->Completed = 1;
                $this->write();
            } else {
                $this->delete();
                $searchEngineDataObject->delete();
            }
        } else {
            $this->delete();
        }
    }

    /**
     * returns all the items that are more than five minutes old
     * @param bool
     * @return DataList
     */
    public static function to_run($oldOnesOnly = false, $limit = 10)
    {
        $objects = SearchEngineDataObjectToBeIndexed::get()
            ->exclude(["SearchEngineDataObjectID" => 0])
            ->filter(["Completed" => 0])
            ->sort(DB::get_conn()->random().' ASC')
            ->limit($limit);

        if ($oldOnesOnly) {
            $objects = $objects->where("UNIX_TIMESTAMP(\"Created\") < ".strtotime("5 minutes ago"));
        }

        return $objects;
    }

    /**
     * Event handler called before deleting from the database.
     */
    public function onBeforeDelete()
    {
        $this->flushCache();
        parent::onBeforeDelete();
        $this->flushCache();
    }

    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        if($obj = $this->SearchEngineDataObject()) {
            $fields->replaceField(
                'SearchEngineDataObjectID',
                ReadonlyField::create(
                    'SearchEngineDataObjectTitle',
                    'Object',
                    DBField::create_field(
                        'HTMLText',
                        '<a href="'.$obj->CMSEditLink().'">'.$obj->getTitle().'</a>'
                    )
                )
            );
        }

        return $fields;
    }
}
