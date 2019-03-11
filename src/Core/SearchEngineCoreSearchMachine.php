<?php

namespace Sunnysideup\SearchSimpleSmart\Core;

use Sunnysideup\SearchSimpleSmart\Api\SearchEngineProviderMYSQLFullText;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Security\Permission;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;
use SilverStripe\Core\Config\Config;
use Sunnysideup\SearchSimpleSmart\Core\SearchEngineCoreSearchMachine;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;
use SilverStripe\View\ViewableData;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Extensible;

class SearchEngineCoreSearchMachine
{

    use Extensible;
    use Injectable;
    use Configurable;

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
     *
     * @var string
     */
    protected $filterProviders = "";

    /**
     *
     * @var string
     */
    protected $sortProvider = "";

    /**
     *
     * @var array
     */
    protected $debugArray = [];

    /**
     *
     * @var boolean
     */
    protected $debug = false;

    /**
     *
     * @var boolean
     */
    protected $bypassCaching = false;

    /**
     * this function runs the Core Search Machine
     * @param string $searchPhrase
     * @param array $filterProviders
     * @param string $sortProvider
     *
     * @return DataList
     */
    public function run($searchPhrase, $filterProviders = array(), $sortProvider = "")
    {
        if ((isset($_GET["searchenginedebug"]) || SiteConfig::current_site_config()->SearchEngineDebug) && Permission::check("SEARCH_ENGINE_ADMIN")) {
            $this->debug = true;
        }
        if (isset($_GET["flush"])) {
            $this->bypassCaching = true;
        }
        //save variables
        $this->filterProviders = $filterProviders;
        $this->sortProvider = $sortProvider;
        if ($this->debug) {
            $startTime = microtime(true);
            $filterExecutedRAW = false;
            $filterExecutedSQL = false;
            $filterExecutedCUSTOM = false;
        }

        //add record
        $searchRecord = SearchEngineSearchRecord::add_search($searchPhrase, $filterProviders, $this->bypassCaching);
        if (!$searchRecord->FinalPhrase) {
            //previous data has been deleted
            //lets retrieve it again.
            $searchRecord->write();
        }
        $listOfIDsRAW = $searchRecord->getListOfIDs("RAW");
        if (!$listOfIDsRAW) {
            if ($this->debug) {
                $filterExecutedRAW = true;
            }
            //run search to search engine specific engine
            $searchProviderName = Config::inst()->get(SearchEngineCoreSearchMachine::class, "class_name_for_search_provision");
            $searchProvider = Injector::inst()->get($searchProviderName);
            $searchProvider->setSearchRecord($searchRecord);
            $dataList = $searchProvider->getRawResults();
            $listOfIDsRAW = $searchRecord->setListOfIDs($dataList, "RAW");
        } else {
            $dataList = SearchEngineDataObject::get()
                ->filter(array("ID" => $listOfIDsRAW))
                ->sort("FIELD(\"ID\", ".implode(",", $listOfIDsRAW).")");
        }

        /**
         * ROUND 1
         * filter and sort the datalist using SQL
         */
        $listOfIDsSQL = $searchRecord->getListOfIDs("SQL");
        $filterStringForDebug = "";
        $filterClassesWithValues = [];
        if (!$listOfIDsSQL) {
            if ($this->debug) {
                $filterExecutedSQL = true;
            }
            if (is_array($filterProviders) && count($filterProviders)) {
                foreach ($filterProviders as $key => $dudd) {
                    list($filterClassName, $filterValue) = explode(".", $key);
                    if (!isset($filterClassesWithValues[$filterClassName])) {
                        $filterClassesWithValues[$filterClassName] = [];
                    }
                    $filterClassesWithValues[$filterClassName][] = $filterValue;
                }
                foreach ($filterClassesWithValues as $filterClassName => $filterValues) {
                    $filterObjects[$filterClassName] = $filterClassName::create();
                    if ($filter = $filterObjects[$filterClassName]->getSqlFilterArray($filterValues, $this->debug)) {
                        $dataList = $dataList->filter($filter);
                        if ($this->debug) {
                            $filterStringForDebug .= print_r($filter, 1);
                        }
                    }
                }
            }
            $listOfIDsSQL = $searchRecord->setListOfIDs($dataList, "SQL");
        } else {
            $dataList = SearchEngineDataObject::get()
                ->filter(array("ID" => $listOfIDsSQL))
                ->sort("FIELD(\"ID\", ".implode(",", $listOfIDsRAW).")");
        }
        $hasCustomSort = false;
        $sqlSort = [];
        if ($sortProvider) {
            $sortProviderObject = $sortProvider::create();
            $hasCustomSort = $sortProviderObject->hasCustomSort();
            $sortArray = $sortProviderObject->getSqlSortArray($this->debug);
            if (is_array($sortArray) && count($sortArray)) {
                $dataList = $dataList->sort($sortArray);
            }
        }

        /**
         * ROUND 2
         * second round of filtering and sorting
         */
        $listOfIDsCUSTOM = $searchRecord->getListOfIDs("CUSTOM");
        if ($listOfIDsCUSTOM === null) {
            if ($this->debug) {
                $filterExecutedCUSTOM = true;
            }
            foreach ($filterClassesWithValues as $filterClassName => $filterValues) {
                if ($filterObjects[$filterClassName] && $filterObjects[$filterClassName]->hasCustomFilter($filterValues)) {
                    if ($this->debug) {
                        $startTimeForCustomFilter = microtime(true);
                    }
                    $dataList = $filterObjects[$filterClassName]->doCustomFilter($dataList, $searchRecord, $filterValues, $this->debug);
                    if ($this->debug) {
                        $endTimeForCustomFilter = microtime(true);
                    }
                }
            }
            $listOfIDsCUSTOM = $searchRecord->setListOfIDs($dataList, "CUSTOM");
        } else {
            $dataList = SearchEngineDataObject::get()->filter(array("ID" => $listOfIDsCUSTOM))->sort($sqlSort);
        }
        if ($hasCustomSort) {
            if ($this->debug) {
                $startTimeForCustomSort = microtime(true);
            }
            $dataList = $sortProviderObject->doCustomSort($dataList, $searchRecord, $this->debug);
            if ($this->debug) {
                $endTimeForCustomSort = microtime(true);
            }
        }

        /**
         * Debug
         *
         */
        if ($this->debug) {
            $endTime = microtime(true);
            $this->debugArray[] = "Finalised Search in ".round(($endTime-$startTime), 4)." seconds. ";
            $this->debugArray[] = "Total RAM used: up to ".round(memory_get_peak_usage()/(1024*1024))."mb.";
            $this->debugArray[] = "---------------- DB Objects --------------";
            $this->debugArray[] = "Object Count: ".SearchEngineDataObject::get()->count();
            $this->debugArray[] = "Keyword Count: ".SearchEngineKeyword::get()->count();
            $this->debugArray[] = "---------------- Data Provided --------------";
            $this->debugArray[] = "Filters: <pre>".print_r($filterProviders, 1)."</pre>";
            $this->debugArray[] = "Sorters: <pre>".print_r($sortProvider, 1)."</pre>";
            $this->debugArray[] = "Phrase Entered: <pre>".print_r($searchRecord->Phrase, 1)."</pre>";
            $this->debugArray[] = "Cleaned Searched: <pre>".print_r($searchRecord->FinalPhrase, 1)."</pre>";
            $keywordArray = [];
            foreach ($searchRecord->SearchEngineKeywords() as $keyword) {
                if (!isset($keywordArray[$keyword->KeywordPosition])) {
                    $keywordArray[$keyword->KeywordPosition] = [];
                }
                $keywordArray[$keyword->KeywordPosition][] = $keyword->Keyword;
            }
            foreach ($keywordArray as $position => $keywords) {
                $keywordArray[$position] = implode(" OR ", $keywords);
            }
            $this->debugArray[] = "Keywords SQL (excludes keywords that are not in index): <pre> (".implode(") AND (", $keywordArray).")</pre>";
            $this->debugArray[] = "---------------- Filters --------------";
            $filter1 = " (". (($filterExecutedRAW) ?    "executed " : "from cache")  ."): carried out by: ".$searchProviderName."";
            $filter2 = " (". (($filterExecutedSQL) ?    "executed"  : "from cache")  ."): <pre>".print_r($filterStringForDebug, 1)."</pre>";
            $filter3 = " (". (($filterExecutedCUSTOM) ? "executed"  : "from cache")  ."): ".((!empty($startTimeForCustomFilter)) ? "YES - seconds taken: ".round($endTimeForCustomSort - $startTimeForCustomSort, 5) : "NO")."";
            $matches1 = (is_array($listOfIDsRAW) ? count($listOfIDsRAW).": <pre>".print_r(implode(",", $listOfIDsRAW), 1)."</pre>" : 0);
            $matches2 = (is_array($listOfIDsSQL) ? count($listOfIDsSQL).": <pre>".print_r(implode(",", $listOfIDsSQL), 1)."</pre>" : 0);
            $matches3 = (is_array($listOfIDsCUSTOM) ? count($listOfIDsCUSTOM).": <pre>".print_r(implode(",", $listOfIDsCUSTOM), 1)."</pre>" : 0);

            $this->debugArray[] = "STEP 1: RAW Filter $filter1";
            $this->debugArray[] = "... RAW matches $matches1";
            $this->debugArray[] = "STEP 2: SQL Filter $filter2";
            $this->debugArray[] = "... SQL matches $matches2";
            $this->debugArray[] = "STEP 3: CUSTOM Filter $filter3";
            $this->debugArray[] = "... CUSTOM matches $matches3";
            $this->debugArray[] = "---------------- Sorting --------------";
            $this->debugArray[] = "SQL SORT: <pre>".print_r($sqlSort, 1)."</pre>";
            $this->debugArray[] = "CUSTOM SORT: <pre>".((!empty($startTimeForCustomSort)) ? "YES - seconds taken: ".round($endTimeForCustomSort - $startTimeForCustomSort, 5) : "NO")."</pre>";
        }
        return $dataList;
    }

    /**
     * returns HTML for Debug
     * @return string
     */
    public function getDebugString()
    {
        if ($this->debug) {
            return "<h3>Debug Info</h3><ul><li>".implode("</li><li>", $this->debugArray)."</li></ul>";
        }
        return "";
    }
}
