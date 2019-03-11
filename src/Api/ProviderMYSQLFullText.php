<?php

namespace Sunnysideup\SearchSimpleSmart\Api;

use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;
use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use SilverStripe\View\ViewableData;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSearchEngineProvider;

/**
 * 1. finds DataObjects with the keywords listed.
 *
 * 2. mysql methods:
 *
 *  a. exact phrase match for whole full content
 *  b. exact phrase match for any part of full content
 *  c. natural langue without query expansion
 *  d. natural language with query expansion
 *  e. all keyword match
 *  f. any keyword match
 *  g. all / any keyword match using soundex
 *
 * 3. getting more words
 *  - sounds like
 *  - stemming
 *  - bla
 *  - metaphone match
 *
 * references:
 *  - http://php.net/manual/en/function.levenshtein.php
 *  - http://php.net/manual/en/function.similar-text.php
 *  - http://php.net/manual/en/function.soundex.php
 *  - SELECT * FROM  `SearchEngineKeyword` WHERE Keyword SOUNDS LIKE  "Home" LIMIT 0 , 30
 *  - http://www.php.net/manual/en/function.metaphone.php
 *  - http://www.pythian.com/blog/mysql-sounds-like-vs-full-text-search/
 *
 *
 */
 use SilverStripe\Core\Config\Configurable;
 use SilverStripe\Core\Injector\Injectable;
 use SilverStripe\Core\Extensible;

class SearchEngineProviderMYSQLFullText implements SearchEngineSearchEngineProvider
{

    use Extensible;
    use Injectable;
    use Configurable;

    /*
     * @var array
     */
    protected $searchRecord = null;

    /**
     * @param SearchEngineSearchRecord
     */
    public function setSearchRecord(SearchEngineSearchRecord $searchRecord)
    {
        $this->searchRecord = $searchRecord;
    }

    /**
     * @return DataList of SearchEngineDataObjects
     */
    public function getRawResults()
    {

        //1. find keywords
        $filterArray = array();
        $keywordObjects = SearchEngineKeyword::get()->where("MATCH(\"Keyword\") AGAINST('".$this->searchRecord->FinalPhrase."')");
        $dataObjectArray = array();
        $max = (substr_count($this->searchRecord->FinalPhrase, " ")+2);
        for ($i = 1; $i < $max; $i++) {
            $keywordIDArray = array(0 => 0);
            $rows = DB::query(
                "
                SELECT \"SearchEngineKeywordID\"
                FROM \"SearchEngineSearchRecord_SearchEngineKeywords\"
                WHERE
                    \"KeywordPosition\" = $i
                    AND \"SearchEngineSearchRecordID\" = ".$this->searchRecord->ID
            );
            foreach ($rows as $row) {
                $keywordIDArray[$row["SearchEngineKeywordID"]] = $row["SearchEngineKeywordID"];
            }
            $rowsLevel1 = DB::query("
                SELECT \"SearchEngineDataObjectID\"
                FROM SearchEngineKeyword_SearchEngineDataObjects_Level1
                WHERE \"SearchEngineKeywordID\" IN (".implode(",", $keywordIDArray).")
                GROUP BY \"SearchEngineDataObjectID\"");
            $rowsLevel1Array = array(0 => 0);
            foreach ($rowsLevel1 as $row) {
                $rowsLevel1Array[$row["SearchEngineDataObjectID"]] = $row["SearchEngineDataObjectID"];
            }
            $rowsLevel2 = DB::query("
                SELECT \"SearchEngineDataObjectID\"
                FROM SearchEngineDataObject_SearchEngineKeywords_Level2
                WHERE
                    \"SearchEngineKeywordID\" IN (".implode(",", $keywordIDArray).") AND
                    \"SearchEngineDataObjectID\" NOT IN (".implode(",", $rowsLevel1Array).")
                GROUP BY \"SearchEngineDataObjectID\"
            ");
            $rowsLevel2Array = array(0 => 0);
            foreach ($rowsLevel2 as $row) {
                $rowsLevel2Array[$row["SearchEngineDataObjectID"]] = $row["SearchEngineDataObjectID"];
            }
            $dataObjectArray[$i] = array_merge($rowsLevel1Array, $rowsLevel2Array);
        }
        if (count($dataObjectArray) > 1) {
            $finalArray = call_user_func_array('array_intersect', $dataObjectArray);
        } else {
            $finalArray = $dataObjectArray[1];
        }

        return SearchEngineDataObject::get()->filter(array("ID" => $finalArray));
    }
}
