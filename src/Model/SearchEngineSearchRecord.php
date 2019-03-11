<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use Sunnysideup\SearchSimpleSmart\Api\SearchEngineStemming;

use SilverStripe\Security\Security;
use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecordHistory;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\Tab;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Flushable;

class SearchEngineSearchRecord extends DataObject implements Flushable
{

    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SearchEngineSearchRecord';


    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SearchEngineAdvancedSettings';

    public static function flush()
    {
        if (Security::database_is_ready()) {
            $query = "SHOW TABLES LIKE 'SearchEngineSearchRecord'";
            $tableExists = DB::query($query)->value();
            if ($tableExists) {
                DB::query("
                    UPDATE \"SearchEngineSearchRecord\"
                    SET \"ListOfIDsRAW\" = '', \"ListOfIDsSQL\" = '', \"ListOfIDsCUSTOM\" = '', \"FinalPhrase\" = ''
                ");
            }
            $query = "SHOW TABLES LIKE 'SearchEngineSearchRecord_SearchEngineKeywords'";
            $tableExists = DB::query($query)->value();
            if ($tableExists) {
                DB::query("
                    DELETE FROM \"SearchEngineSearchRecord_SearchEngineKeywords\"
                ");
            }
        }
    }

    /**
     * @var string
     */
    private static $singular_name = "Search Record";
    public function i18n_singular_name()
    {
        return self::$singular_name;
    }

    /**
     * @var string
     */
    private static $plural_name = "Search Records";
    public function i18n_plural_name()
    {
        return self::$plural_name;
    }

    /**
     * @var array
     */
    private static $db = array(
        "Phrase" => "Varchar(255)",
        "FinalPhrase" => "Varchar(255)",
        "FilterHash" => "Varchar(32)",
        "FilterString" => "Text",
        "ListOfIDsRAW" => "Text",
        "ListOfIDsSQL" => "Text",
        "ListOfIDsCUSTOM" => "Text"
    );

    private static $has_many = array(
        "SearchEngineSearchRecordHistory" => SearchEngineSearchRecordHistory::class
    );

    private static $many_many = array(
        "SearchEngineKeywords" => SearchEngineKeyword::class
    );

    private static $many_many_extraFields = array(
        "SearchEngineKeywords" => array("KeywordPosition" => "Int")
    );

    /**
     * @var string
     */
    private static $default_sort = "\"Phrase\" ASC";

    /**
     * @var array
     */
    private static $required_fields = array(
        "Phrase",
        "FinalPhrase"
    );

    /**
     * @var array
     */
    private static $field_labels = array(
        "SearchEngineSearchRecordHistory" => "Search History"
    );

    /**
     * @var array
     */
    private static $indexes = array(
        "Phrase" => true,
        "FilterHash" => true
    );

    /**
     * @var array
     */
    private static $summary_fields = array(
        "Phrase" => "Phrase",
        "FinalPhrase" => "Final Phrase",
        "FilterHash" => "Unique Filter code"
    );

    /**
     * @return boolean
     */
    public function canDelete($member = null, $context = [])
    {
        return false;
    }

    /**
     * @param string $searchPhrase
     * @param array $filterProviders
     * @param boolean $clear
     *
     * @return SearchEngineSearchRecord
     */
    public static function add_search($searchPhrase, $filterProviders, $clear = false)
    {
        $filterProvidersEncoded = "";
        $filterProvidersHashed = "";
        if (is_array($filterProviders) && count($filterProviders)) {
            $filterProvidersEncoded = Convert::raw2sql(serialize($filterProviders));
            $filterProvidersHashed = md5($filterProvidersEncoded);
        }
        $fieldArray = array(
            "Phrase" => $searchPhrase,
            "FilterHash" => $filterProvidersHashed
        );
        $obj = SearchEngineSearchRecord::get()
            ->filter($fieldArray)
            ->first();
        if (!$obj) {
            $obj = SearchEngineSearchRecord::create($fieldArray);
            if ($filterProvidersEncoded) {
                $obj->FilterString = $filterProvidersEncoded;
            }
            $obj->write();
        } elseif ($clear || strtotime($obj->LastEdited) < strtotime("-10 minutes")) {
            $obj->ListOfIDsRAW = "";
            $obj->ListOfIDsSQL = "";
            $obj->ListOfIDsCUSTOM = "";
            //keywords are replaced automatically onAfterWrite
            $obj->write();
        }
        SearchEngineSearchRecordHistory::add_entry($obj);
        return $obj;
    }


    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeFieldFromTab("Root.Main", "FilterHash");
        $fields->removeFieldFromTab("Root.Main", "FilterString");
        $fields->removeFieldFromTab("Root.Main", "ListOfIDsRAW");
        $fields->removeFieldFromTab("Root.Main", "ListOfIDsSQL");
        $fields->removeFieldFromTab("Root.Main", "ListOfIDsCUSTOM");
        $fields->addFieldsToTab(
            'Root',
            array(
                new Tab(
                    'TempCache',
                    new ReadonlyField("FilterHash", "Filter Hash"),
                    new ReadonlyField("FilterString", "Filter String"),
                    new ReadonlyField("ListOfIDsRAW", "Step 1 IDs"),
                    new ReadonlyField("ListOfIDsSQL", "Step 2 IDs"),
                    new ReadonlyField("ListOfIDsCUSTOM", "Step 3 IDs")
                )
            )
        );
        $fields->addFieldsToTab(
            'Root.Main',
            array(
                new ReadonlyField("Phrase", "Phrase"),
                new ReadonlyField("FinalPhrase", "Cleaned Phrase")
            )
        );
        return $fields;
    }

    /**
     * @var boolean
     */
    protected $listOfIDsUpdateOnly = false;

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->listOfIDsUpdateOnly) {
            //nothing more to do
        } else {
            $cleanedPhrase = SearchEngineFullContent::clean_content($this->Phrase);
            $keywords = explode(" ", $cleanedPhrase);
            $finalKeyWordArray = [];
            foreach ($keywords as $keyword) {
                if (SearchEngineKeywordFindAndRemove::is_listed($keyword) || strlen($keyword) == 1) {
                    continue;
                }
                $finalKeyWordArray[$keyword] = $keyword;
            }
            $this->FinalPhrase = implode(" ", $finalKeyWordArray);
        }
    }


    public function onAfterWrite()
    {
        parent::onAfterWrite();
        $this->SearchEngineKeywords()->removeAll();
        $keywordArray = explode(" ", $this->FinalPhrase);
        foreach ($keywordArray as $position => $keyword) {
            $stem = SearchEngineStemming::stem($keyword);
            $realPosition = $position+1;
            $selectArray = array(0 => 0);
            $keywordsAfterFindReplace = explode(" ", SearchEngineKeywordFindAndReplace::find_replacements($keyword));
            $keywordsAfterFindReplace[] = $keyword;
            $keywordsAfterFindReplace = array_unique($keywordsAfterFindReplace);
            $whereArray = [];
            foreach ($keywordsAfterFindReplace as $innerPosition => $innerKeyword) {
                $length = strlen($innerKeyword);
                if ($length < 2) {
                    //do nothing
                } elseif ($length < 4) {
                    $whereArray[] = '"Keyword" = \''.$innerKeyword.'\'';
                } else {
                    if ($stem && $stem != $keyword) {
                        $whereArray[] = "\"Keyword\" LIKE '".$stem."%'";
                    }
                    $whereArray[] = "\"Keyword\" LIKE '".$innerKeyword."%'";
                }
            }
            if (count($whereArray)) {
                $where = "(".
                    implode(") OR (", $whereArray).
                    ")";
                $keywords = SearchEngineKeyword::get()
                    ->where($where)
                    ->exclude(array("ID" => $selectArray))
                    ->limit(5);
                $selectArray +=  $keywords->map("ID", "ID")->toArray();
                foreach ($selectArray as $id) {
                    $this->SearchEngineKeywords()->add($id, array("KeywordPosition" => $realPosition));
                }
            }
        }
    }

    /**
     * saves the IDs of the DataList
     *
     * note that it returns the list as an array
     * to match getListOfIDs
     *
     * @param SS_List
     * @param $filterStep ("RAW", "SQL", "CUSTOM")
     *
     * @return array
     */
    public function setListOfIDs($list, $filterStep)
    {
        $field = $this->getListIDField($filterStep);
        if ($list && $list instanceof SS_List && $list->count()) {
            $this->$field = implode(",", $list->map("ID", "ID")->toArray());
        } else {
            $this->$field = "-1";
        }
        $this->listOfIDsUpdateOnly = true;
        $this->write();
        return $this->$field;
    }

    /**
     * saves the IDs of the DataList
     * @param SS_List
     * @param $filterStep ("RAW", "SQL", "CUSTOM")
     *
     * @return Array
     */
    public function getListOfIDs($filterStep)
    {
        $field = $this->getListIDField($filterStep);
        if ($this->$field) {
            return explode(",", $this->$field);
        } else {
            return null;
        }
    }

    /**
     * @param $filterStep ("RAW", "SQL", "CUSTOM")
     *
     * @return string
     */
    protected function getListIDField($filterStep)
    {
        if (!in_array($filterStep, array("RAW", "SQL", "CUSTOM"))) {
            user_error("$filterStep Filterstep Must Be in RAW / SQL / CUSTOM");
        }
        return "ListOfIDs".$filterStep;
    }
}
