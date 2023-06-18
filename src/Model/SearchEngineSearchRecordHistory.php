<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Control\Controller;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * the DataObject ClassName + ID is recorded separately
 * so that the log is not affected if the SearchEngineDataObject is deleted.
 */
class SearchEngineSearchRecordHistory extends DataObject
{
    protected static $_latest_search_cache;

    /**
     * Defines the database table name.
     *
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
        'ClickedOnDataObjectClassName' => 'Varchar(150)',
        'ClickedOnDataObjectID' => 'Int',
        'NumberOfResults' => 'Int',
        'Session' => 'Varchar(7)',
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
    private static $field_labels = [
        'Created' => 'When',
        'Phrase' => 'Searched for',
        'NumberOfResults' => 'Results offered',
        'Session' => 'User code',
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Created' => 'When',
        'Phrase' => 'Searched for',
        'NumberOfResults' => 'Results offered',
        'Session' => 'User code',
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'Phrase' => true,
        'ClickedOnDataObjectClassName' => true,
        'ClickedOnDataObjectID' => true,
    ];

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
        return false;
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
     * add an entry SearchEngineSearchRecordHistory entry.
     *
     * @param SearchEngineSearchRecord $searchEngineSearchRecord
     */
    public static function add_entry($searchEngineSearchRecord)
    {
        //a real request - lets start a new search record history ...
        $currentUserID = 0;
        $currentUser = Security::getCurrentUser();
        if ($currentUser) {
            $currentUserID = $currentUser->ID;
        }

        $fieldArray = [
            'SearchEngineSearchRecordID' => $searchEngineSearchRecord->ID,
            'Phrase' => $searchEngineSearchRecord->Phrase,
            'MemberID' => $currentUserID - 0,
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
     * add an entry SearchEngineSearchRecordHistory entry.
     *
     * @param int $count
     *
     * @return null|SearchEngineSearchRecordHistory
     */
    public static function add_number_of_results($count)
    {
        $obj = self::get_latest_search();
        if ($obj) {
            $obj->NumberOfResults = $count;
            $obj->write();

            return $obj;
        }
        return null;
    }

    /**
     * Records what the user clicked on... from the search results.
     *
     * @param SearchEngineDataObject $item
     *
     * @return null|SearchEngineSearchRecordHistory
     */
    public static function register_click($item)
    {
        $obj = self::get_latest_search();
        if ($obj && ($item instanceof SearchEngineDataObject)) {
            $obj->ClickedOnDataObjectClassName = $item->DataObjectClassName;
            $obj->ClickedOnDataObjectID = $item->DataObjectID;
            $obj->write();

            return $obj;
        }
        return null;
    }

    public function set_latest_search(SearchEngineSearchRecord $item)
    {
        self::$_latest_search_cache = self::get()->byID($id);
    }

    /**
     * @return null|SearchEngineSearchRecordHistory
     */
    public static function get_latest_search()
    {
        if (null === self::$_latest_search_cache) {
            self::$_latest_search_cache = false;
            $id = (int) Controller::curr()->getRequest()->getSession()->get('SearchEngineSearchRecordHistoryID') - 0;
            if ($id) {
                self::$_latest_search_cache = self::get()->byID($id);
            }
        }

        return self::$_latest_search_cache;
    }
}
