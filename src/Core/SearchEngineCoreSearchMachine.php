<?php

namespace Sunnysideup\SearchSimpleSmart\Core;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Permission;
use SilverStripe\SiteConfig\SiteConfig;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineCoreMachineProvider;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSearchEngineProvider;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;
use Sunnysideup\SearchSimpleSmart\Api\FasterIDLists;
use Sunnysideup\SearchSimpleSmart\Api\SearchEngineProviderMYSQLFullText;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecordHistory;
use Sunnysideup\SearchSimpleSmart\Sorters\SearchEngineSortByRelevance;

class SearchEngineCoreSearchMachine implements SearchEngineCoreMachineProvider
{
    use Extensible;
    use Injectable;
    use Configurable;


    public const NO_KEYWORD_PROVIDED = '[NO KEYWORD PROVIDED]';

    /**
     * ClassNameForFilter => ValuesToFilterFor...
     *
     * @var array
     */
    protected $filterProviders = [];

    /**
     * @var string
     */
    protected $sortProvider = SearchEngineSortByRelevance::class;

    /**
     * @var mixed
     */
    protected $sortProviderValues;

    /**
     * @var array
     */
    protected $debugArray = [];

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var bool
     */
    protected $bypassCaching = false;

    /**
     * @var float
     */
    protected $startTimeForRun = 0;

    /**
     * @var float
     */
    protected $endTimeForRun = 0;

    /**
     * @var float
     */
    protected $startTimeForCustomFilter = 0;

    /**
     * @var float
     */
    protected $endTimeForCustomFilter = 0;

    /**
     * @var float
     */
    protected $startTimeForCustomSort = 0;

    /**
     * @var float
     */
    protected $endTimeForCustomSort = 0;

    /**
     * @var bool
     */
    protected $filterExecutedRAW = false;

    /**
     * @var bool
     */
    protected $filterExecutedSQL = false;

    /**
     * @var bool
     */
    protected $filterExecutedCustom = false;

    /**
     * @var SearchEngineSearchRecord
     */
    protected $searchRecord;

    /**
     * @var string
     */
    protected $searchProviderName = '';

    /**
     * @var null|SearchEngineSearchEngineProvider
     */
    protected $searchProvider = null;

    /**
     * @var array
     */
    protected $listOfIDsAsArray = [];

    /**
     * @var array
     */
    protected $filterObjects = [];

    /**
     * @var array
     */
    protected $listOfIDsSQLAsArray = [];

    /**
     * @var bool
     */
    protected $hasCustomSort = false;

    /**
     * @var array
     */
    protected $nonCustomSort = [];

    /**
     * @var string
     */
    protected $nameForSortProvider = '';

    /**
     * @var null|SearchEngineSortByDescriptor
     */
    protected $sortProviderObject;

    /**
     * @var array
     */
    protected $sortArray = [];

    /**
     * @var array
     */
    protected $listOfIDsCustomAsArray = [];

    /**
     * @var string
     */
    protected $filterStringForDebug = '';

    /**
     * @var string
     */
    protected $searchPhrase = '';

    /**
     * @var null|DataList
     */
    protected $dataList;

    /**
     * @var null|DataList
     */
    protected $filter;

    /**
     * @var array
     */
    protected $keywordArray = [];

    /**
     * @var string
     */
    protected $customFilterTime = '';

    /**
     * @var string
     */
    protected $customSortTime = '';

    protected $filter1;

    protected $filter2;

    protected $filter3 = '';

    protected $matches1;

    protected $matches2;

    protected $matches3 = '';

    /**
     * class used to provide the raw results
     * raw results are the SearchEngineDataObject matches for a particular keyword
     * phrase, without any filters or sort.
     * This is the base collection.
     *
     * @var string
     */
    private static $class_name_for_search_provision = SearchEngineProviderMYSQLFullText::class;

    /**
     * @param string $filterClassName
     * @param array  $filterValues
     *
     * @return $this
     */
    public function addFilter($filterClassName, $filterValues)
    {
        $this->filterProviders[$filterClassName] = $filterValues;

        return $this;
    }

    /**
     * @param string|null     $sortProvider       classname of a sort provider
     * @param null|mixed      $sortProviderValues values for the sorting - if any
     *
     * @return $this
     */
    public function setSorter($sortProvider, $sortProviderValues = null)
    {
        $this->sortProvider = $sortProvider;
        $this->sortProviderValues = $sortProviderValues;

        return $this;
    }

    public function setDebug($bool)
    {
        $this->debug = $bool;

        return $this;
    }

    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * this function runs the Core Search Machine.
     *
     * @param string $searchPhrase
     * @param array  $filterProviders
     * @param string $sortProvider
     * @param object $sortProviderValues
     *
     * @return DataList
     */
    public function run(?string $searchPhrase = '', ?array $filterProviders = [], ?string $sortProvider = '', $sortProviderValues = null)
    {
        if(strlen(trim($searchPhrase)) < 2) {
            $searchPhrase = self::NO_KEYWORD_PROVIDED;
        }
        // special get vars
        $this->runGetGetVars();

        //initialise vars
        $this->runInitVars($searchPhrase, $filterProviders, $sortProvider, $sortProviderValues);

        $this->runGetSearchRecord();

        $this->runCreateFilters();

        $this->runGetPreviouslySavedData();

        $this->runFilterUsingSQL();

        $this->runSortUsingSQL();

        $this->runFilterUsingCustom();

        $this->runSortUsingCustom();

        $this->markResults();

        if ($this->debug) {
            $this->runDebugPrep1();
            $this->runDebugPrep2();
            $this->runDebugOutput();
        }

        return $this->dataList;
    }

    public function ConvertDataListToOriginalObjects($dataList, $limit = 500)
    {
        $al = ArrayList::create();
        $dataList = $dataList->limit($limit);
        foreach ($dataList as $dataListItem) {
            $item = $dataListItem->SourceObject();
            if ($item) {
                $al->push($item);
            }
        }

        return $al;
    }

    /**
     * returns HTML for Debug.
     *
     * @return string
     */
    public function getDebugString()
    {
        if ($this->debug) {
            return '<h3>Debug Info</h3><ul><li>' . implode('</li><li>', $this->debugArray) . '</li></ul>';
        }

        return '';
    }

    protected function runGetGetVars()
    {
        $test1 = (! empty($_GET['searchenginedebug']) || SiteConfig::current_site_config()->SearchEngineDebug);
        $test2 = Permission::check('SEARCH_ENGINE_ADMIN');
        if ($test1 && $test2) {
            $this->debug = true;
        }

        if (isset($_GET['flush'])) {
            $this->bypassCaching = true;
        }

        if ($this->debug) {
            $this->startTimeForRun = microtime(true);
        }
    }

    protected function runInitVars(?string $searchPhrase = '', ?array $filterProviders = [], ?string $sortProvider = '', $sortProviderValues = null)
    {
        $this->searchPhrase = $searchPhrase;

        //save variables
        if (! empty($filterProviders)) {
            $this->filterProviders += $filterProviders;
        }

        if (! empty($sortProvider)) {
            $this->sortProvider = $sortProvider;
        }

        if (! empty($sortProviderValues)) {
            $this->sortProviderValues = $sortProviderValues;
        }
    }

    protected function runGetSearchRecord()
    {
        //add record
        $this->searchRecord = SearchEngineSearchRecord::add_search($this->searchPhrase, $this->filterProviders, $this->bypassCaching);
        if (! $this->searchRecord->HasCachedData) {
            //previous data has been deleted
            //lets retrieve it again.
            $this->searchRecord->write();
        }

        //cached data ...
        $this->listOfIDsAsArray = $this->searchRecord->getListOfIDs('RAW');
        $this->listOfIDsSQLAsArray = $this->searchRecord->getListOfIDs('SQL');
        $this->listOfIDsCustomAsArray = $this->searchRecord->getListOfIDs('CUSTOM');
    }

    protected function runCreateFilters()
    {
        if (is_array($this->filterProviders) && count($this->filterProviders)) {
            foreach ($this->filterProviders as $filterClassName => $filterValues) {
                $filterClassName = (string) $filterClassName;
                $this->filterObjects[$filterClassName] = $filterClassName::create($this->debug)
                    ->setFilterValues($filterValues)
                ;
            }
        }
    }

    protected function runGetPreviouslySavedData()
    {
        if (is_array($this->listOfIDsSQLAsArray) && count($this->listOfIDsSQLAsArray)) {
            //skip
            return;
        }

        $this->searchProviderName = 'not set';
        if (is_array($this->listOfIDsAsArray) && count($this->listOfIDsAsArray)) {
            $this->dataList = Injector::inst()->create(
                FasterIDLists::class,
                SearchEngineDataObject::class,
                $this->listOfIDsAsArray
            )->filteredDatalist();

            $this->dataList = $this->dataList->orderBy('FIELD("ID", ' . implode(',', $this->listOfIDsAsArray) . ')');
            // $this->dataList = SearchEngineDataObject::get()
            //     ->filter(['ID' => $this->listOfIDsAsArray])
            //     ->orderBy('FIELD("ID", ' . $this->listOfIDsAsString . ')');
        } else {
            if ($this->debug) {
                $this->filterExecutedRAW = true;
            }

            //run search to search engine specific engine
            $this->searchProviderName = Config::inst()->get(self::class, 'class_name_for_search_provision');

            /** @var SearchEngineSearchEngineProvider $this->searchProvider */
            $this->searchProvider = Injector::inst()->get($this->searchProviderName);
            $this->searchProvider->setSearchRecord($this->searchRecord);
            //get search objects that match the keywords
            $this->dataList = $this->searchProvider->getRawResults();

            // storing the RAW results.
            $this->listOfIDsAsArray = explode(',', $this->searchRecord->setListOfIDs($this->dataList, 'RAW'));
        }
    }

    protected function runFilterUsingSQL()
    {
        if (is_array($this->listOfIDsCustomAsArray) && count($this->listOfIDsCustomAsArray)) {
            //skip
            return;
        }

        if (is_array($this->listOfIDsSQLAsArray) && count($this->listOfIDsSQLAsArray)) {
            $this->dataList = Injector::inst()->create(
                FasterIDLists::class,
                SearchEngineDataObject::class,
                $this->listOfIDsSQLAsArray
            )->filteredDatalist();

            $this->dataList = $this->dataList
                ->orderBy('FIELD("ID", ' . implode(',', $this->listOfIDsSQLAsArray) . ')')
            ;

            // $this->dataList = SearchEngineDataObject::get()
            //     ->filter(['ID' => $this->listOfIDsSQLAsArray])
            //     ->orderBy('FIELD("ID", ' . $this->listOfIDsSQLString . ')');
        } else {
            if ($this->debug) {
                $this->filterExecutedSQL = true;
            }

            foreach ($this->filterProviders as $filterClassName => $filterValues) {
                // print_r($this->dataList->sql());
                $this->filter = $this->filterObjects[$filterClassName]->getSqlFilterArray($filterValues);
                if ($this->filter) {
                    foreach ($this->filter as $field => $idList) {
                        if ('ID' === $field) {
                            $where = Injector::inst()->create(
                                FasterIDLists::class,
                                SearchEngineDataObject::class,
                                $idList,
                                $field
                            )->shortenIdList();
                            $this->dataList = $this->dataList->where($where);
                        } else {
                            $this->dataList = $this->dataList->filter([$field => $idList]);
                        }
                    }

                    if ($this->debug) {
                        $this->filterStringForDebug .= $this->fancyPrintR($this->filter);
                    }
                }
            }

            // storing the SQL results.
            $this->listOfIDsSQLAsArray = explode(',', $this->searchRecord->setListOfIDs($this->dataList, 'SQL'));
        }
    }

    protected function runSortUsingSQL()
    {
        $this->hasCustomSort = false;
        $this->nonCustomSort = [];
        if ($this->sortProvider) {
            $this->nameForSortProvider = $this->sortProvider;
            $sorterClassName = $this->sortProvider;
            $this->sortProviderObject = $sorterClassName::create($this->debug);
            $this->hasCustomSort = $this->sortProviderObject->hasCustomSort($this->sortProviderValues);
            $this->sortArray = $this->sortProviderObject->getSqlSortArray($this->sortProviderValues);
            if (is_array($this->sortArray) && count($this->sortArray)) {
                $this->nonCustomSort = $this->sortArray;
                $this->dataList = $this->dataList->sort($this->sortArray);
            }
        }
    }

    protected function runFilterUsingCustom()
    {
        if (is_array($this->listOfIDsCustomAsArray) && count($this->listOfIDsCustomAsArray)) {
            $this->dataList = Injector::inst()->create(
                FasterIDLists::class,
                SearchEngineDataObject::class,
                $this->listOfIDsCustomAsArray
            )->filteredDatalist();
            if(is_array($this->nonCustomSort) && count($this->nonCustomSort)) {
                $this->dataList = $this->dataList->sort($this->nonCustomSort);
            } elseif(is_string($this->nonCustomSort) && strlen($this->nonCustomSort)) {
                $this->dataList = $this->dataList->orderBy($this->nonCustomSort);
            }
            // $this->dataList = SearchEngineDataObject::get()
            // ->filter(['ID' => $this->listOfIDsCustomAsArray])
            // ->orderBy($this->nonCustomSort);
        } else {
            if ($this->debug) {
                $this->filterExecutedCustom = true;
            }

            foreach ($this->filterProviders as $filterClassName => $filterValues) {
                if ($this->filterObjects[$filterClassName]->hasCustomFilter($filterValues)) {
                    if ($this->debug) {
                        $this->startTimeForCustomFilter = microtime(true);
                    }

                    $this->dataList = $this->filterObjects[$filterClassName]->doCustomFilter($this->dataList, $this->searchRecord, $filterValues);
                    if ($this->debug) {
                        $this->endTimeForCustomFilter = microtime(true);
                    }
                }
            }

            // storing the custom results
            $this->listOfIDsCustomAsArray = explode(',', $this->searchRecord->setListOfIDs($this->dataList, 'CUSTOM'));
        }
    }

    protected function runSortUsingCustom()
    {
        if ($this->hasCustomSort) {
            if ($this->debug) {
                $this->startTimeForCustomSort = microtime(true);
            }

            if ($this->sortProviderObject) {
                $this->dataList = $this->sortProviderObject->doCustomSort($this->dataList, $this->searchRecord);
            }

            if ($this->debug) {
                $this->endTimeForCustomSort = microtime(true);
            }
        }
    }

    protected function markResults()
    {
        SearchEngineSearchRecordHistory::add_number_of_results(
            $this->searchRecord,
            count($this->listOfIDsCustomAsArray)
        );
    }

    protected function runDebugPrep1()
    {
        $this->endTimeForRun = microtime(true);
        $this->keywordArray = [];
        foreach ($this->searchRecord->SearchEngineKeywords() as $keyword) {
            if (! isset($this->keywordArray[$keyword->KeywordPosition])) {
                $this->keywordArray[$keyword->KeywordPosition] = [];
            }

            $this->keywordArray[$keyword->KeywordPosition][] = $keyword->Keyword;
        }

        foreach ($this->keywordArray as $position => $keywords) {
            $this->keywordArray[$position] = implode(' OR ', $keywords);
        }

        $this->customFilterTime = (empty($this->startTimeForCustomFilter) ?
            'NO'
            :
            'YES - seconds taken: ' . round($this->endTimeForCustomFilter - $this->startTimeForCustomSort, 5)) . '';
        $this->customSortTime = (empty($this->startTimeForCustomSort) ?
            'NO'
            :
            'YES - seconds taken: ' . round($this->endTimeForCustomSort - $this->startTimeForCustomSort, 5)) . '</pre>';
    }

    protected function runDebugPrep2()
    {
        $this->filter1 = ' (' . ($this->filterExecutedRAW ? 'executed ' : 'from cache') . '): carried out by: ' . $this->searchProviderName . '';
        $this->filter2 = ' (' . ($this->filterExecutedSQL ? 'executed' : 'from cache') . '): <pre>' . print_r($this->filterStringForDebug, 1) . '</pre>';
        $this->filter3 = ' (' . ($this->filterExecutedCustom ? 'executed' : 'from cache') . '): ' . $this->customFilterTime;
        $this->matches1 =
            count($this->listOfIDsAsArray) .
            ': <pre>' . $this->fancyPrintIDList($this->listOfIDsAsArray) . '</pre>';
        $this->matches2 =
            count($this->listOfIDsSQLAsArray) .
            ': <pre>' . $this->fancyPrintIDList($this->listOfIDsSQLAsArray) . '</pre>';
        $this->matches3 =
            count($this->listOfIDsCustomAsArray) .
            ': <pre>' . $this->fancyPrintIDList($this->listOfIDsCustomAsArray) . '</pre>';
    }

    protected function runDebugOutput()
    {
        $this->debugArray[] = 'Finalised Search in ' . round(($this->endTimeForRun - $this->startTimeForRun), 4) . ' seconds. ';
        $this->debugArray[] = 'Total RAM used: up to ' . round(memory_get_peak_usage() / (1024 * 1024)) . 'mb.';
        $this->debugArray[] = '---------------- DB Objects --------------';
        $this->debugArray[] = 'Object Count: ' . SearchEngineDataObject::get()->count();
        $this->debugArray[] = 'Keyword Count: ' . SearchEngineKeyword::get()->count();
        $this->debugArray[] = '---------------- Data Provided --------------';
        $this->debugArray[] = 'Filters: <pre>' . print_r(array_keys($this->filterProviders), 1) . '</pre>';
        $this->debugArray[] = 'Sorters: <pre>' . print_r($this->sortProvider, 1) . '</pre>';
        $this->debugArray[] = 'Phrase Entered: <pre>' . print_r($this->searchRecord->Phrase, 1) . '</pre>';
        $this->debugArray[] = 'Cleaned Searched: <pre>' . print_r($this->searchRecord->FinalPhrase, 1) . '</pre>';
        $this->debugArray[] = 'Keywords SQL (excludes keywords that are not in index): <pre> (' . implode(') AND (', $this->keywordArray) . ')</pre>';
        $this->debugArray[] = '---------------- Filters --------------';
        $this->debugArray[] = 'STEP 1: RAW Filter ' . $this->filter1;
        $this->debugArray[] = "... RAW matches {$this->matches1}";
        $this->debugArray[] = '<hr />';
        $this->debugArray[] = 'STEP 2: SQL Filter ' . $this->filter2;
        $this->debugArray[] = '... SQL matches ' . $this->matches2;
        $this->debugArray[] = '<hr />';
        $this->debugArray[] = 'STEP 3: CUSTOM Filter ' . $this->filter3;
        $this->debugArray[] = '... CUSTOM matches ' . $this->matches3;
        $this->debugArray[] = '<hr />';
        $this->debugArray[] = '---------------- Sorting --------------';
        $this->debugArray[] = '<hr />';
        $this->debugArray[] = 'SQL SORT: <pre>' . print_r($this->nonCustomSort, 1) . '</pre>';
        $this->debugArray[] = 'CUSTOM SORT: <pre>' . $this->customSortTime;
        $this->debugArray[] = '<hr />';
    }

    private function fancyPrintIDList($array)
    {
        $newArray = [];
        foreach ($array as $key => $id) {
            $obj = SearchEngineDataObject::get()->byID($id);
            $newArray[$key] = $obj ? $obj->getTitle() : 'ERROR - could not find SearchEngineDataObject with ID: ' . $id;
        }

        return $this->fancyPrintR($newArray, 10000);
    }

    private function fancyPrintR($array, $limit = 50)
    {
        if (is_array($array)) {
            if (count($array) <= $limit) {
                foreach ($array as $key => $values) {
                    if (is_array($values)) {
                        return '
                        <br />' . $key . ': ' . $this->fancyPrintR($values);
                    }

                    $this->fancyPrintR($key);
                }

                return print_r($array, 1);
            }

            return print_r(array_splice($array, 0, $limit), 1) . '
                <br />... AND MORE';
        }

        return print_r($array, 1);
    }
}
