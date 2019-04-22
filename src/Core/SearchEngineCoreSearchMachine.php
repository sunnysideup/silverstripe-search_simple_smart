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
use Sunnysideup\SearchSimpleSmart\Sorters\SearchEngineSortByRelevance;
use Sunnysideup\SearchSimpleSmart\Sorters\SearchEngineSortByDate;
use SilverStripe\View\ViewableData;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Extensible;
use SilverStripe\ORM\ArrayList;

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
     * ClassNameForFilter => ValuesToFilterFor...
     * @var array
     */
    protected $filterProviders = [];

    /**
     *
     * @var string
     */
    protected $sortProvider = SearchEngineSortByRelevance::class;

    /**
     *
     * @var mixed
     */
    protected $sortProviderValues = null;

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
    protected $bypassCaching = true;

    /**
     *
     * @param [type] $filterClassName [description]
     * @param [type] $filterValues    [description]
     *
     * @return this
     */
    public function addFilter($filterClassName, $filterValues)
    {
        $this->filterProviders[$filterClassName] = $filterValues;

        return $this;
    }

    /**
     *
     * @param string $sortProvider classname of a sort provider
     * @param mixed|null $sortProviderValues    values for the sorting - if any
     *
     * @return this
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
     *
     * this function runs the Core Search Machine
     * @param string $searchPhrase
     * @param array $filterProviders
     * @param string $sortProvider
     *
     * @return DataList
     */
    public function run($searchPhrase, $filterProviders = [], $sortProvider = '', $sortProviderValues = null)
    {
        if ((!empty($_GET["searchenginedebug"]) || SiteConfig::current_site_config()->SearchEngineDebug) && Permission::check("SEARCH_ENGINE_ADMIN")) {
            $this->debug = true;
        }
        if (isset($_GET["flush"])) {
            $this->bypassCaching = true;
        }
        //save variables
        $this->filterProviders += $filterProviders;
        if($sortProvider) {
            $this->sortProvider = $sortProvider;
            $this->sortProviderValues = $sortProviderValues;
        }
        if ($this->debug) {
            $startTime = microtime(true);
            $filterExecutedRAW = false;
            $filterExecutedSQL = false;
            $filterExecutedCUSTOM = false;
        }

        //add record
        $searchRecord = SearchEngineSearchRecord::add_search($searchPhrase, $this->filterProviders, $this->bypassCaching);
        if (!$searchRecord->FinalPhrase) {
            //previous data has been deleted
            //lets retrieve it again.
            $searchRecord->write();
        }

        //check previously saved data.
        $listOfIDsRAW = $searchRecord->getListOfIDs("RAW");
        if (!$listOfIDsRAW) {
            if ($this->debug) {
                $filterExecutedRAW = true;
            }
            //run search to search engine specific engine
            $searchProviderName = Config::inst()->get(SearchEngineCoreSearchMachine::class, "class_name_for_search_provision");
            $searchProvider = Injector::inst()->get($searchProviderName);
            $searchProvider->setSearchRecord($searchRecord);
            //get search objects that match the keywords
            $dataList = $searchProvider->getRawResults();
            $listOfIDsRAW = $searchRecord->setListOfIDs($dataList, "RAW");
        } else {
            $dataList = SearchEngineDataObject::get()
                ->filter(array("ID" => $listOfIDsRAW))
                ->sort("FIELD(\"ID\", ".implode(",", $listOfIDsRAW).")");
        }

        //create filters
        if (is_array($this->filterProviders) && count($this->filterProviders)) {
            foreach ($this->filterProviders as $filterClassName => $filterValues) {
                $filterObjects[$filterClassName] = $filterClassName::create($this->debug);
            }
        }

        /**
         * ROUND 1
         * filter and sort the datalist using SQL
         */
        //get cached value for SQL ID List.
        $listOfIDsSQL = $searchRecord->getListOfIDs("SQL");
        $filterStringForDebug = "";
        $filterClassesWithValues = [];
        if (!$listOfIDsSQL) {
            if ($this->debug) {
                $filterExecutedSQL = true;
            }
            foreach($this->filterProviders as $filterClassName => $filterValues) {
                if ($filter = $filterObjects[$filterClassName]->getSqlFilterArray($filterValues)) {
                    $dataList = $dataList->filter($filter);
                    if ($this->debug) {
                        $filterStringForDebug .= $this->fancyPrintR($filter);
                    }
                }
            }
            $listOfIDsSQL = $searchRecord->setListOfIDs($dataList, "SQL");
        } else {
            $dataList = SearchEngineDataObject::get()
                ->filter(array("ID" => $listOfIDsSQL))
                ->sort("FIELD(\"ID\", ".implode(",", $listOfIDsSQL).")");
        }
        $hasCustomSort = false;
        $nonCustomSort = [];
        if ($this->sortProvider) {
            $name = $this->sortProvider;
            $sortProviderObject = $name::create($this->debug);
            $hasCustomSort = $sortProviderObject->hasCustomSort($this->sortProviderValues);
            $sortArray = $sortProviderObject->getSqlSortArray($this->sortProviderValues);
            if (is_array($sortArray) && count($sortArray)) {
                $nonCustomSort = $sortArray;
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
            foreach($this->filterProviders as $filterClassName => $filterValues) {
                if ($filterObjects[$filterClassName]->hasCustomFilter($filterValues)) {
                    if ($this->debug) {
                        $startTimeForCustomFilter = microtime(true);
                    }
                    $dataList = $filterObjects[$filterClassName]->doCustomFilter($dataList, $searchRecord, $filterValues);
                    if ($this->debug) {
                        $endTimeForCustomFilter = microtime(true);
                    }
                }
            }
            $listOfIDsCUSTOM = $searchRecord->setListOfIDs($dataList, "CUSTOM");
        } else {
            $dataList = SearchEngineDataObject::get()->filter(array("ID" => $listOfIDsCUSTOM))->sort($nonCustomSort);
        }
        if ($hasCustomSort) {
            if ($this->debug) {
                $startTimeForCustomSort = microtime(true);
            }
            $dataList = $sortProviderObject->doCustomSort($dataList, $searchRecord);
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
            $this->debugArray[] = "Filters: <pre>".print_r(array_keys($this->filterProviders), 1)."</pre>";
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
            $listOfIDsRAW = explode(',', $listOfIDsRAW);
            $listOfIDsSQL = explode(',', $listOfIDsSQL);
            $listOfIDsCUSTOM = explode(',', $listOfIDsCUSTOM);
            $matches1 = (is_array($listOfIDsRAW) ? count($listOfIDsRAW).": <pre>".$this->fancyPrintIDList($listOfIDsRAW)."</pre>" : 0);
            $matches2 = (is_array($listOfIDsSQL) ? count($listOfIDsSQL).": <pre>".$this->fancyPrintIDList($listOfIDsSQL)."</pre>" : 0);
            $matches3 = (is_array($listOfIDsCUSTOM) ? count($listOfIDsCUSTOM).": <pre>".$this->fancyPrintIDList($listOfIDsCUSTOM)."</pre>" : 0);

            $this->debugArray[] = "STEP 1: RAW Filter $filter1";
            $this->debugArray[] = "... RAW matches $matches1";
            $this->debugArray[] = "<hr />";
            $this->debugArray[] = "STEP 2: SQL Filter $filter2";
            $this->debugArray[] = "... SQL matches $matches2";
            $this->debugArray[] = "<hr />";
            $this->debugArray[] = "STEP 3: CUSTOM Filter $filter3";
            $this->debugArray[] = "... CUSTOM matches $matches3";
            $this->debugArray[] = "<hr />";
            $this->debugArray[] = "---------------- Sorting --------------";
            $this->debugArray[] = "<hr />";
            $this->debugArray[] = "SQL SORT: <pre>".print_r($nonCustomSort, 1)."</pre>";
            $this->debugArray[] = "CUSTOM SORT: <pre>".((!empty($startTimeForCustomSort)) ? "YES - seconds taken: ".round($endTimeForCustomSort - $startTimeForCustomSort, 5) : "NO")."</pre>";
            $this->debugArray[] = "<hr />";
        }

        return $dataList;
    }

    public function ConvertDataListToOriginalObjects($dataList, $limit = 500)
    {
        $al = ArrayList::create();
        $dataList = $dataList->limit($limit);
        foreach($dataList as $dataListItem)
        {
            $item = $dataListItem->SourceObject();
            if($item) {
                $al->push($item);
            }
        }

        return $al;
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

    private function fancyPrintIDList($array)
    {
        $newArray = [];
        foreach($array as $key => $id) {
            $obj = SearchEngineDataObject::get()->byID($id);
            if($obj) {
                $newArray[$key] = $obj->getTitle();
            } else {
                $newArray[$key] = "ERROR - could not find SearchEngineDataObject with ID: ".$id;
            }
        }

        return $this->fancyPrintR($newArray, 10000);
    }

    private function fancyPrintR($array, $limit = 50)
    {
        if(is_array($array)) {
            if(count($array) <= $limit) {
                foreach($array as $key => $values) {
                    if(is_array($values)) {
                        return '
                        <br />'.$key.': '.$this->fancyPrintR($values);
                    } else {
                        $this->fancyPrintR($key);
                    }
                }
                return print_r($array, 1);
            } else {
                return print_r(array_splice($array, 0, $limit), 1).'
                <br />... AND MORE';
            }
        } else {
            return print_r($array, 1);
        }
    }
}
