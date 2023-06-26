<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
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

    private static $minutes_to_keep_search_as_one_search_for_user = 20;

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
        'Session' => 'Int',
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

    private static $searchable_fields = [
        'Phrase' => 'PartialMatchFilter',
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'Phrase' => true,
        'Session' => true,
        'ClickedOnDataObjectClassName' => true,
        'ClickedOnDataObjectID' => true,
    ];


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

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('ClickedDataObjectClassName');
        $fields->removeByName('ClickedDataObjectID');
        return $fields;
    }

    /**
     * add an entry SearchEngineSearchRecordHistory entry.
     *
     * @param SearchEngineSearchRecord $searchEngineSearchRecord
     */
    public static function add_entry($searchEngineSearchRecord)
    {

        //update latest search
        $obj = self::get_latest_search($searchEngineSearchRecord);

        $obj->write();

        return $obj;
    }

    /**
     * add an entry SearchEngineSearchRecordHistory entry.
     *
     * @param int $count
     *
     * @return null|SearchEngineSearchRecordHistory
     */
    public static function add_number_of_results($searchEngineSearchRecord, ?int $count = 0)
    {
        //update latest search
        $obj = self::get_latest_search($searchEngineSearchRecord);
        if ($obj) {
            if(! $count) {
                $count = substr_count($searchEngineSearchRecord->ListOfIDsCUSTOM, ',') + 1;
            }
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
    public static function register_click($searchEngineSearchRecord, $item)
    {
        $obj = self::get_latest_search($searchEngineSearchRecord);
        if ($obj && ($item instanceof SearchEngineDataObject)) {
            $obj->ClickedOnDataObjectClassName = $item->DataObjectClassName;
            $obj->ClickedOnDataObjectID = $item->DataObjectID;
            $obj->write();

            return $obj;
        }
        return null;
    }

    /**
     * @return null|SearchEngineSearchRecordHistory
     */
    public static function get_latest_search($searchEngineSearchRecord): SearchEngineSearchRecordHistory
    {
        if (null === self::$_latest_search_cache) {
            //a real request - lets start a new search record history ...
            $currentUserID = 0;
            $currentUser = Security::getCurrentUser();
            if ($currentUser) {
                $currentUserID = $currentUser->ID;
            }
            $fieldArray = [
                'Phrase' => $searchEngineSearchRecord->Phrase,
                'MemberID' => $currentUserID,
                'Session' => self::get_timestamp_based_session_id(),
                'SearchEngineSearchRecordID' => $searchEngineSearchRecord->ID,
            ];
            self::$_latest_search_cache = SearchEngineSearchRecordHistory::get()
                ->filter($fieldArray)
                ->sort(['ID' => 'DESC'])
                ->first();
            if(! self::$_latest_search_cache) {
                self::$_latest_search_cache = SearchEngineSearchRecordHistory::create($fieldArray);
                self::$_latest_search_cache->write();
            }
        }

        return self::$_latest_search_cache;
    }


    public static function set_timestamp_based_session_id_if_not_set(?int $number = 0): int
    {
        $curr = self::get_timestamp_based_session_id();
        if(!$curr || ($number && $number !== $curr)) {
            $curr = self::set_timestamp_based_session_id($number);
        }
        return $curr;
    }

    public static function set_timestamp_based_session_id(?int $number = 0): int
    {
        $session = self::get_session();
        if($session) {
            if(! $number) {
                $number = time();
            }
            $session->set('SearchEngineSearchRecordHistorySessionID', $number);
        } else {
            $number = 0;
        }
        return $number;
    }

    public static function get_timestamp_based_session_id(): int
    {
        $session = self::get_session();
        if($session) {
            $val = (int) $session->get('SearchEngineSearchRecordHistorySessionID');
            // check for expiry
            if(! $val || $val < self::get_session_expiry_time()) {
                $val = self::set_timestamp_based_session_id();
            }
            return $val;
        }
        return 0;
    }

    private static function get_session_expiry_time(): int
    {
        $minutes = Config::inst()->get(static::class, 'minutes_to_keep_search_as_one_search_for_user') ?: 10;
        $minus = $minutes * 60;
        return time() - $minus;
    }

    protected static $_session_cache = null;

    protected static function get_session()
    {
        if(! self::$_session_cache) {
            $controller = Controller::curr();
            if($controller) {
                $request = $controller->getRequest();
                if($request) {
                    self::$_session_cache = $request->getSession();
                }
            }
        }
        return self::$_session_cache;
    }
}
