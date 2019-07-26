<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Control\Controller;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * the DataObject ClassName + ID is recorded separately
 * so that the log is not affected if the SearchEngineDataObject is deleted.
 */

class SearchEngineSearchRecordHistory extends DataObject
{
    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SearchEngineSearchRecordHistory';

    /**
     * @var string
     */
    private static $singular_name = 'Search History';

    /**
     * @var string
     */
    private static $plural_name = 'Search History';

    /**
     * @var array
     */
    private static $db = [
        'Phrase' => 'Varchar(150)',
        'DataObjectClassName' => 'Varchar(150)',
        'DataObjectID' => 'Int',
        'NumberOfResults' => 'Int',
        'Session' => 'Varchar(32)',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'SearchEngineSearchRecord' => SearchEngineSearchRecord::class,
        'Member' => Member::class,
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Created' => 'Created',
        'Phrase' => 'Phrase',
        'DataObjectClassName' => 'Class',
        'DataObjectID' => 'ID',
        'NumberOfResults' => 'Result Count',
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'Phrase' => true,
        'DataObjectClassName' => true,
        'DataObjectID' => true,
    ];

    private static $_latest_search_cache = null;

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
    public function canEdit($member = null)
    {
        return false;
    }

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canDelete($member = null)
    {
        return false;
    }

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canView($member = null)
    {
        return parent::canView() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * add an entry SearchEngineSearchRecordHistory entry
     * @param SearchEngineSearchRecord $searchEngineSearchRecord
     */
    public static function add_entry($searchEngineSearchRecord)
    {
        //a real request - lets start a new search record history ...
        $fieldArray = [
            'SearchEngineSearchRecordID' => $searchEngineSearchRecord->ID,
            'MemberID' => intval(Member::currentUserID()) - 0,
            'Session' => session_id(),
        ];
        //update latest search
        $obj = self::get_latest_search();
        if ($obj) {
            foreach ($fieldArray as $field => $value) {
                $obj->{$field} = $value;
            }
        } else {
            $obj = DataObject::get_one(self::class, $fieldArray);
            if (! $obj) {
                $obj = self::create($fieldArray);
            }
        }

        Controller::curr()->getRequest()->getSession()->set('SearchEngineSearchRecordHistoryID', $obj->write());
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

    /**
     * @return SearchEngineSearchRecordHistory | null
     */
    public static function get_latest_search()
    {
        if (self::$_latest_search_cache === null) {
            self::$_latest_search_cache = false;
            $id = intval(Controller::curr()->getRequest()->getSession()->get('SearchEngineSearchRecordHistoryID')) - 0;
            if ($id) {
                self::$_latest_search_cache = self::get()->byID($id);
            }
        }
        return self::$_latest_search_cache;
    }
}
