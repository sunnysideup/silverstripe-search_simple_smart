<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use Wamania\Snowball\StemmerFactory;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Flushable;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\Tab;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\SS_List;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

class SearchEngineSearchRecord extends DataObject implements Flushable
{
    /**
     * @var bool
     */
    protected $listOfIDsUpdateOnly = false;

    /**
     * defaults to three months
     * @var int
     */
    private static $max_cache_age_in_minutes = 129600;

    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SearchEngineSearchRecord';

    /**
     * @var string
     */
    private static $singular_name = 'Search Record';

    /**
     * @var string
     */
    private static $plural_name = 'Search Records';

    /**
     * @var array
     */
    private static $db = [
        'Phrase' => 'Varchar(255)',
        'FinalPhrase' => 'Varchar(255)',
        'FilterHash' => 'Varchar(32)',
        'FilterString' => 'Text',
        'ListOfIDsRAW' => 'Text',
        'ListOfIDsSQL' => 'Text',
        'ListOfIDsCUSTOM' => 'Text',
    ];

    private static $has_many = [
        'SearchEngineSearchRecordHistory' => SearchEngineSearchRecordHistory::class,
    ];

    private static $many_many = [
        'SearchEngineKeywords' => SearchEngineKeyword::class,
    ];

    private static $many_many_extraFields = [
        'SearchEngineKeywords' => ['KeywordPosition' => 'Int'],
    ];

    /**
     * @var string
     */
    private static $default_sort = '"Phrase" ASC';

    /**
     * @var array
     */
    private static $required_fields = [
        'Phrase',
        'FinalPhrase',
    ];

    /**
     * @var array
     */
    private static $field_labels = [
        'SearchEngineSearchRecordHistory' => 'Search History',
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'Phrase' => true,
        'FilterHash' => true,
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Phrase' => 'Phrase',
        'FinalPhrase' => 'Final Phrase',
        'FilterHash' => 'Unique Filter code',
    ];

    /**
     * clears all records
     */
    public static function flush()
    {
        if (Security::database_is_ready()) {
            $query = "SHOW TABLES LIKE 'SearchEngineSearchRecord'";
            $tableExists = DB::query($query)->value();
            if ($tableExists) {
                DB::query("
                    UPDATE \"SearchEngineSearchRecord\"
                    SET
                        \"ListOfIDsRAW\" = '',
                        \"ListOfIDsSQL\" = '',
                        \"ListOfIDsCUSTOM\" = '',
                        \"FinalPhrase\" = ''
                ");
            }
            $query = "SHOW TABLES LIKE 'SearchEngineSearchRecord_SearchEngineKeywords'";
            $tableExists = DB::query($query)->value();
            if ($tableExists) {
                DB::query('
                    DELETE FROM "SearchEngineSearchRecord_SearchEngineKeywords"
                ');
            }
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

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canEdit($member = null)
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return false;
    }

    public function canView($member = null)
    {
        return parent::canView() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param string $searchPhrase
     * @param array $filterProviders
     * @param bool $clear
     *
     * @return SearchEngineSearchRecord
     */
    public static function add_search($searchPhrase, $filterProviders, $clear = false)
    {
        $filterProvidersEncoded = '';
        $filterProvidersHashed = '';
        if (is_array($filterProviders) && count($filterProviders)) {
            $filterProvidersEncoded = Convert::raw2sql(serialize($filterProviders));
            $filterProvidersHashed = md5($filterProvidersEncoded);
        }
        $fieldArray = [
            'Phrase' => $searchPhrase,
            'FilterHash' => $filterProvidersHashed,
        ];
        $obj = DataObject::get_one(self::class, $fieldArray);
        if (! $obj) {
            $obj = self::create($fieldArray);
            if ($filterProvidersEncoded) {
                $obj->FilterString = $filterProvidersEncoded;
            }
            $obj->write();
        } else {
            $maxAge = Config::inst()->get(self::class, 'max_cache_age_in_minutes');
            if ($clear || strtotime($obj->LastEdited) < strtotime('-' . $maxAge . ' minutes')) {
                $obj->ListOfIDsRAW = '';
                $obj->ListOfIDsSQL = '';
                $obj->ListOfIDsCUSTOM = '';
                //keywords are replaced automatically onAfterWrite
                $obj->write();
            }
        }
        SearchEngineSearchRecordHistory::add_entry($obj);

        return $obj;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeFieldFromTab('Root.Main', 'FilterHash');
        $fields->removeFieldFromTab('Root.Main', 'FilterString');
        $fields->removeFieldFromTab('Root.Main', 'ListOfIDsRAW');
        $fields->removeFieldFromTab('Root.Main', 'ListOfIDsSQL');
        $fields->removeFieldFromTab('Root.Main', 'ListOfIDsCUSTOM');
        $fields->addFieldsToTab(
            'Root',
            [
                new Tab(
                    'TempCache',
                    new ReadonlyField('FilterHash', 'Filter Hash'),
                    new ReadonlyField('FilterString', 'Filter String'),
                    new ReadonlyField('ListOfIDsRAW', 'Step 1 IDs'),
                    new ReadonlyField('ListOfIDsSQL', 'Step 2 IDs'),
                    new ReadonlyField('ListOfIDsCUSTOM', 'Step 3 IDs')
                ),
            ]
        );
        $fields->addFieldsToTab(
            'Root.Main',
            [
                new ReadonlyField('Phrase', 'Phrase'),
                new ReadonlyField('FinalPhrase', 'Cleaned Phrase'),
            ]
        );
        return $fields;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->listOfIDsUpdateOnly) {
            //nothing more to do
        } else {
            $cleanedPhrase = SearchEngineFullContent::clean_content($this->Phrase);
            $keywords = explode(' ', $cleanedPhrase);
            $finalKeyWordArray = [];
            foreach ($keywords as $keyword) {
                if (SearchEngineKeywordFindAndRemove::is_listed($keyword) || strlen($keyword) === 1) {
                    continue;
                }
                $finalKeyWordArray[$keyword] = $keyword;
            }
            $this->FinalPhrase = implode(' ', $finalKeyWordArray);
        }
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        $this->SearchEngineKeywords()->removeAll();
        $keywordArray = explode(' ', $this->FinalPhrase);
        foreach ($keywordArray as $position => $keyword) {
            $stemmer = StemmerFactory::create('en');
            $stem = $stemmer->stem($keyword);
            $realPosition = $position + 1;
            $selectArray = [0 => 0];
            $keywordsAfterFindReplace = explode(' ', SearchEngineKeywordFindAndReplace::find_replacements($keyword));
            $keywordsAfterFindReplace[] = $keyword;
            $keywordsAfterFindReplace = array_unique($keywordsAfterFindReplace);
            $whereArray = [];
            foreach ($keywordsAfterFindReplace as $innerKeyword) {
                $length = strlen($innerKeyword);
                if ($length < 2) {
                    //do nothing
                } elseif ($length < 4) {
                    $whereArray[] = '"Keyword" = \'' . $innerKeyword . '\'';
                } else {
                    if ($stem && $stem !== $keyword) {
                        $whereArray[] = "\"Keyword\" LIKE '" . $stem . "%'";
                    }
                    $whereArray[] = "\"Keyword\" LIKE '" . $innerKeyword . "%'";
                }
            }
            if (count($whereArray)) {
                $where = '(' .
                    implode(') OR (', $whereArray) .
                    ')';
                $keywords = SearchEngineKeyword::get()
                    ->where($where)
                    ->exclude(['ID' => $selectArray])
                    ->limit(999);
                $selectArray += $keywords->map('ID', 'ID')->toArray();
                foreach ($selectArray as $id) {
                    if ($id) {
                        $this->SearchEngineKeywords()->add($id, ['KeywordPosition' => $realPosition]);
                    }
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
     * @param mixed   $list
     * @param string  $filterStep ("RAW", "SQL", "CUSTOM")
     *
     * @return string
     */
    public function setListOfIDs($list, string $filterStep): string
    {
        $field = $this->getListIDField($filterStep);
        //default to nothing
        $this->{$field} = -1;
        if ($list) {
            if ($list instanceof SS_List && $list->count()) {
                return $this->setListOfIDs($list->column('ID'), $filterStep);
            } elseif (is_string($list)) {
                return $this->setListOfIDs(explode(',', $list), $filterStep);
            } elseif (is_array($list)) {
                $this->{$field} = implode(',', array_unique($list));
            }
        }
        $this->listOfIDsUpdateOnly = true;
        $this->write();

        return $this->{$field};
    }

    /**
     * saves the IDs of the DataList
     * @param string $filterStep
     *
     * @return array|null
     */
    public function getListOfIDs(string $filterStep): ?array
    {
        $field = $this->getListIDField($filterStep);
        if ($this->{$field}) {
            return explode(',', $this->{$field});
        }
        return null;
    }

    /**
     * @param string $filterStep
     *
     * @return string
     */
    protected function getListIDField(string $filterStep): string
    {
        if (! in_array($filterStep, ['RAW', 'SQL', 'CUSTOM'], true)) {
            user_error("${filterStep} Filterstep Must Be in RAW / SQL / CUSTOM");
        }
        return 'ListOfIDs' . $filterStep;
    }
}
