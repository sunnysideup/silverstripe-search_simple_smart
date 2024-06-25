<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Flushable;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\SS_List;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use Wamania\Snowball\StemmerFactory;

class SearchEngineSearchRecord extends DataObject implements Flushable
{
    /**
     * defaults to three months.
     *
     * @var int
     */
    private static $max_cache_age_in_minutes = 129600;

    /**
     * Defines the database table name.
     *
     * @var string
     */
    private static $table_name = 'SearchEngineSearchRecord';

    /**
     * @var string
     */
    private static $singular_name = 'Keywords Searched Entry';

    /**
     * @var string
     */
    private static $plural_name = 'Keywords Searched Entries';

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
        'HasCachedData' => 'Boolean',
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
        'FilterHash' => 'Filter Code (if any)',
        'SearchEngineSearchRecordHistory.Count' => 'Search Count',
        'NumberOfResults' => 'Results offered',
    ];

    /**
     * @var array
     */
    private static $searchable_fields = [
        'Phrase' => 'PartialMatchFilter',
        'FinalPhrase' => 'PartialMatchFilter',
    ];

    private static $casting = [
        'NumberOfResults' => 'Int',
    ];

    public function getLastSearchResult(): ?SearchEngineSearchRecordHistory
    {
        return $this->SearchEngineSearchRecordHistory()->sort(['ID' => 'DESC'])->first();
    }

    public function getNumberOfResults(): int
    {
        $obj = $this->getLastSearchResult();
        if ($obj instanceof \Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecordHistory) {
            return $obj->NumberOfResults;
        }
        return 0;
    }

    /**
     * clears all records
     * from their cache data
     */
    public static function flush()
    {
        if (Security::database_is_ready()) {
            $query = "SHOW TABLES LIKE 'SearchEngineSearchRecord'";
            $tableExists = DB::query($query)->value();
            if ($tableExists && DB::get_schema()->hasField('SearchEngineSearchRecord', 'HasCachedData')) {
                DB::query(
                    "
                                UPDATE \"SearchEngineSearchRecord\"
                                SET
                                    \"ListOfIDsRAW\" = '',
                                    \"ListOfIDsSQL\" = '',
                                    \"ListOfIDsCUSTOM\" = '',
                                    \"FinalPhrase\" = '',
                                    \"HasCachedData\" = 0
                            "
                );
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

    protected function doesFieldExist($tableName, $fieldName)
    {
        // SQL query to check if the column exists
        $query = sprintf("SHOW COLUMNS FROM `%s` LIKE '%s'", $tableName, $fieldName);

        // Execute the SQL query
        $result = DB::query($query);
        // Check if the result has data
        return $result->numRecords() > 0;
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
     * @param bool   $clear
     *
     * @return SearchEngineSearchRecord|null
     */
    public static function add_search(string $searchPhrase, array $filterProviders, $clear = false)
    {
        $filterProvidersEncoded = '';
        $filterProvidersHashed = '';
        if ($filterProviders !== []) {
            $filterProvidersEncoded = Convert::raw2sql(serialize($filterProviders));
            $filterProvidersHashed = md5($filterProvidersEncoded);
        }

        $fieldArray = [
            'Phrase' => $searchPhrase,
        ];
        if ($filterProvidersHashed !== '' && $filterProvidersHashed !== '0') {
            $fieldArray['FilterHash'] = $filterProvidersHashed;
        }
        /** @var SearchEngineSearchRecord $obj */
        $obj = DataObject::get_one(self::class, $fieldArray);
        if (! $obj) {
            $obj = self::create($fieldArray);
            if ($filterProvidersEncoded) {
                $obj->FilterString = $filterProvidersEncoded;
            }
            $obj->write();
        } elseif ($clear || self::cachedValuesAreExpired($obj)) {
            $obj->ListOfIDsRAW = '';
            $obj->ListOfIDsSQL = '';
            $obj->ListOfIDsCUSTOM = '';
            $obj->HasCachedData = false;
            //keywords are replaced automatically onAfterWrite
            $obj->write();
        }

        SearchEngineSearchRecordHistory::add_entry($obj);

        return $obj;
    }

    protected static function cachedValuesAreExpired(SearchEngineSearchRecord $obj): bool
    {
        if (Director::isDev()) {
            return true;
        }
        $maxAge = Config::inst()->get(static::class, 'max_cache_age_in_minutes');
        return strtotime($obj->LastEdited) < strtotime('-' . $maxAge . ' minutes');
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab(
            'Root.Main',
            [
                new ReadonlyField('Phrase', 'Phrase'),
                new ReadonlyField('FinalPhrase', 'Cleaned Phrase'),
            ]
        );

        $fields->removeFieldFromTab('Root.Main', 'FilterHash');
        $fields->removeFieldFromTab('Root.Main', 'FilterString');
        $fields->removeFieldFromTab('Root.Main', 'ListOfIDsRAW');
        $fields->removeFieldFromTab('Root.Main', 'ListOfIDsSQL');
        $fields->removeFieldFromTab('Root.Main', 'ListOfIDsCUSTOM');
        $fields->addFieldsToTab(
            'Root.CacheDetails',
            [
                new ReadonlyField('FilterHash', 'Filter Hash'),
                new ReadonlyField('FilterString', 'Filter String'),
                new ReadonlyField('ListOfIDsRAW', 'Step 1 IDs'),
                new ReadonlyField('ListOfIDsSQL', 'Step 2 IDs'),
                new ReadonlyField('ListOfIDsCUSTOM', 'Step 3 IDs'),
                new ReadonlyField('cachedValuesAreExpiredNice', 'Cache valid?', self::cachedValuesAreExpired($this) ? 'NO' : 'YES'),
            ]
        );

        return $fields;
    }

    /**
     * saves the IDs of the DataList.
     *
     * note that it returns the list as an array
     * to match getListOfIDs
     *
     * @param mixed  $list
     * @param string $filterStep ("RAW", "SQL", "CUSTOM")
     */
    public function setListOfIDs($list, string $filterStep): string
    {
        $field = $this->getListIDField($filterStep);
        //default to nothing
        $this->{$field} = -1;
        if ($list) {
            if ($list instanceof SS_List && $list->count()) {
                return $this->setListOfIDs($list->column('ID'), $filterStep);
            }

            if (is_string($list)) {
                return $this->setListOfIDs(explode(',', $list), $filterStep);
            }

            if (is_array($list)) {
                $this->{$field} = implode(',', array_unique($list));
            }
        }

        $this->write();

        return $this->{$field};
    }

    /**
     * saves the IDs of the DataList.
     */
    public function getListOfIDs(string $filterStep): ?array
    {
        $field = $this->getListIDField($filterStep);
        if ($this->{$field}) {
            return explode(',', $this->{$field});
        }

        return null;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->FinalPhrase = $this->convertPhraseToFinalPhrase();
    }

    protected function convertPhraseToFinalPhrase(): string
    {
        $cleanedPhrase = SearchEngineFullContent::clean_content($this->Phrase);
        $keywords = explode(' ', $cleanedPhrase);
        $finalKeyWordArray = [];
        foreach ($keywords as $keyword) {
            if (SearchEngineKeywordFindAndRemove::is_listed($keyword) || 1 === strlen($keyword)) {
                continue;
            }

            $finalKeyWordArray[$keyword] = $keyword;
        }

        return implode(' ', $finalKeyWordArray);
    }


    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        if (! $this->HasCachedData) {
            $this->attachKeywords();
            $this->HasCachedData = true;
            $this->write();
        }
    }

    protected function getListIDField(string $filterStep): string
    {
        if (! in_array($filterStep, ['RAW', 'SQL', 'CUSTOM'], true)) {
            user_error("{$filterStep} Filterstep Must Be in RAW / SQL / CUSTOM");
        }

        return 'ListOfIDs' . $filterStep;
    }

    protected function attachKeywords()
    {
        $rel = $this->SearchEngineKeywords();
        $rel->removeAll();
        $keywordArray = explode(' ', $this->FinalPhrase);
        foreach ($keywordArray as $position => $keyword) {
            $language = substr(i18n::get_locale(), 0, 2);
            if (! strlen($language) === 2) {
                $language = 'en';
            }
            $stemmer = StemmerFactory::create($language);
            $stem = $stemmer->stem($keyword);
            $realPosition = $position + 1;
            $selectArray = [-1 => 0];
            $keywordsAfterFindReplace = explode(' ', SearchEngineKeywordFindAndReplace::find_replacements($keyword));
            $keywordsAfterFindReplace[] = $keyword;
            $keywordsAfterFindReplace = array_unique($keywordsAfterFindReplace);
            $whereArray = [];
            foreach ($keywordsAfterFindReplace as $innerKeyword) {
                $length = strlen($innerKeyword);
                if ($length < 2) {
                    //do nothing
                } elseif ($length < 4) {
                    $whereArray[] = '"Keyword" = \'' . $innerKeyword . "'";
                } else {
                    if ($stem && $stem !== $keyword) {
                        $whereArray[] = "\"Keyword\" LIKE '" . $stem . "%'";
                    }

                    $whereArray[] = "\"Keyword\" LIKE '" . $innerKeyword . "%'";
                }
            }

            if ([] !== $whereArray) {
                $where = '(' .
                    implode(') OR (', $whereArray) .
                    ')';
                $keywords = SearchEngineKeyword::get()
                    ->where($where)
                    ->exclude(['ID' => $selectArray])
                    ->limit(999)
                ;
                $newOnes = $keywords->columnUnique();
                $selectArray = array_unique(array_merge($selectArray, $newOnes));
                foreach ($selectArray as $id) {
                    if ($id) {
                        $rel->add($id, ['KeywordPosition' => $realPosition]);
                    }
                }
            }
        }
    }
}
