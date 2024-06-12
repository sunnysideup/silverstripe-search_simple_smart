<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * presents a list of dataobjects
 * that need to be reindexed, because they have changed.
 *
 * Once they have been indexed, they will be removed again.
 */
class SearchEngineDataObjectToBeIndexed extends DataObject
{
    protected static $_cache_for_items = [];

    /**
     * Defines the database table name.
     *
     * @var string
     */
    private static $table_name = 'SearchEngineDataObjectToBeIndexed';

    /**
     * @var string
     */
    private static $singular_name = 'To Be (re)Indexed';

    /**
     * @var string
     */
    private static $plural_name = 'To Be (re)Indexed';

    /**
     * @var array
     */
    private static $db = [
        'Completed' => 'Boolean(1)',
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'Completed' => true,
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'SearchEngineDataObject' => SearchEngineDataObject::class,
    ];

    /**
     * @var array
     */
    private static $required_fields = [
        'SearchEngineDataObject',
    ];

    /**
     * @var array
     */
    private static $field_labels = [
        'SearchEngineDataObject' => 'Searchable Object',
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Title' => 'Searchable Object',
        'Created' => 'Added On',
        'Completed.Nice' => 'Completed',
    ];

    /**
     * Defines a default list of filters for the search context.
     *
     * @var array
     */
    private static $searchable_fields = [
        'Completed' => 'ExactMatchFilter',
    ];

    /**
     * @var array
     */
    private static $casting = [
        'Title' => 'Varchar',
    ];

    /**
     * @var array
     */
    private static $default_sort = [
        'Completed' => 'ASC',
        'Created' => 'DESC',
    ];

    /**
     * you must set this to true once you have your cron job
     * up and running.
     * The cron job runs this task every ?? minutes.
     *
     * @var bool
     */
    private static $cron_job_running = false;

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
        if ($this->SearchEngineDataObjectID) {
            $obj = $this->SearchEngineDataObject();
            if ($obj) {
                return $obj->getTitle();
            }
        }

        return 'ERROR';
    }

    /**
     * @param mixed $alsoIndex
     *
     * @return null|SearchEngineDataObjectToBeIndexed
     */
    public static function add(SearchEngineDataObject $searchEngineDataObject, $alsoIndex = true)
    {
        if ($searchEngineDataObject && $searchEngineDataObject->exists()) {
            if (! isset(self::$_cache_for_items[$searchEngineDataObject->ID])) {
                $fieldArray = [
                    'SearchEngineDataObjectID' => $searchEngineDataObject->ID,
                    'Completed' => 0,
                ];
                $objToBeIndexedRecord = DataObject::get_one(
                    self::class,
                    $fieldArray
                );
                if ($objToBeIndexedRecord && $objToBeIndexedRecord->exists()) {
                    //do nothing
                } else {
                    $objToBeIndexedRecord = self::create($fieldArray);
                    $objToBeIndexedRecord->write();
                }

                //we do not want this on DEV
                if (Config::inst()->get(self::class, 'cron_job_running')) {
                    //cron will take care of it...
                } elseif ($alsoIndex) {
                    $objToBeIndexedRecord->IndexNow($searchEngineDataObject);
                }

                self::$_cache_for_items[$searchEngineDataObject->ID] = $objToBeIndexedRecord;
            }

            return self::$_cache_for_items[$searchEngineDataObject->ID];
        }

        user_error('The SearchEngineDataObject needs to exist');

        return null;
    }

    public function IndexNow(?SearchEngineDataObject $searchEngineDataObject = null)
    {
        if (! $searchEngineDataObject instanceof \Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject) {
            $searchEngineDataObject = $this->SearchEngineDataObject();
        }

        if ($searchEngineDataObject && $searchEngineDataObject->exists() && $searchEngineDataObject instanceof SearchEngineDataObject) {
            $sourceObject = $searchEngineDataObject->SourceObject();
            if ($sourceObject && $sourceObject->exists() && $sourceObject->SearchEngineExcludeFromIndex() !== true) {
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
     * returns all the items that are more than five minutes old.
     *
     * @param bool  $oldOnesOnly
     * @param mixed $limit
     *
     * @return DataList
     */
    public static function to_run($oldOnesOnly = false, $limit = 10)
    {
        $objects = self::get()
            ->exclude(['SearchEngineDataObjectID' => 0])
            ->filter(['Completed' => 0])
            ->shuffle()
            ->limit($limit)
        ;

        if ($oldOnesOnly) {
            $objects = $objects->where('UNIX_TIMESTAMP("Created") < ' . strtotime('5 minutes ago'));
        }

        return $objects;
    }

    /**
     * CMS Fields.
     *
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $obj = $this->SearchEngineDataObject();
        if ($obj) {
            $fields->replaceField(
                'SearchEngineDataObjectID',
                ReadonlyField::create(
                    'SearchEngineDataObjectTitle',
                    'Object',
                    DBField::create_field(
                        'HTMLText',
                        '<a href="' . $obj->CMSEditLink() . '">' . $obj->getTitle() . '</a>'
                    )
                )
            );
        }

        return $fields;
    }

    /**
     * Event handler called before deleting from the database.
     */
    protected function onBeforeDelete()
    {
        $this->flushCache();
        parent::onBeforeDelete();
        $this->flushCache();
    }
}
