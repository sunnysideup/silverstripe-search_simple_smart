<?php

namespace Sunnysideup\SearchSimpleSmart\Sorters;

use SilverStripe\Core\Convert;
use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;

/**
 * default sort option
 */

class SearchEngineSortByRelevance extends SearchEngineSortByDescriptor
{
    /**
     * @return string
     */
    public function getShortTitle()
    {
        return _t('SearchEngineSortByRelevance.TITLE', 'Relevance');
    }

    /**
     * returns the description - e.g. "sort by the last Edited date"
     * @return string
     */
    public function getDescription()
    {
        return $this->getShortTitle();
    }

    /**
     * returns the sort statement that is addeded to search
     * query prior to searching the SearchEngineDataObjects
     *
     * return an array like
     *     Date => ASC
     *     Title => DESC
     *
     * @param mixed $sortProviderValues
     *
     * @return array
     */
    public function getSqlSortArray($sortProviderValues = null)
    {
        return [];
    }

    /**
     * @return boolean
     */
    public function hasCustomSort($sortProviderValues = null)
    {
        return true;
    }

    /**
     * Do any custom sorting
     *
     * @param SS_List $objects
     * @param SearchEngineSearchRecord $searchRecord
     *
     * @return SS_List
     */
    public function doCustomSort($objects, $searchRecord)
    {
        if ($objects->count() < 2) {
            //do nothing
        } else {
            $array = [0 => -1];
            $fromSQL = '
                FROM "SearchEngineFullContent"
                    INNER JOIN "SearchEngineDataObject"
                        ON "SearchEngineDataObject"."ID" = "SearchEngineFullContent"."SearchEngineDataObjectID"
            ';
            $sortSQL = '
                ORDER BY
                    "Level",
                    RELEVANCE DESC
            ';

            //look for complete phrase if there is more than one word.
            //exact full match of search phrase using relevance, level 1 first
            //and further upfront in text as second sort by.
            if (strpos(trim($searchRecord->Phrase), ' ')) {
                $sql = '
                    SELECT
                        "SearchEngineDataObject"."ID" AS MyID,
                        (999999 - LOCATE(\'' . Convert::raw2sql($searchRecord->Phrase) . '\',"Content")) AS RELEVANCE
                    ' . $fromSQL . '
                    WHERE
                        "Content" LIKE \'%' . Convert::raw2sql($searchRecord->Phrase) . '%\'
                        AND "SearchEngineDataObjectID" IN (' . $searchRecord->ListOfIDsCUSTOM . ')
                    ' . $sortSQL . '
                ;';
                $rows = DB::query($sql);
                foreach ($rows as $row) {
                    $id = $row['MyID'];
                    if (! isset($array[$id])) {
                        $array[$id] = $row['RELEVANCE'];
                    }
                }
            }
            //fulltext using relevance, level 1 first.
            $sql = '
                SELECT
                    "SearchEngineDataObject"."ID" AS MyID,
                    MATCH ("Content") AGAINST (\'' . $searchRecord->FinalPhrase . '\') AS RELEVANCE
                ' . $fromSQL . '
                WHERE
                    "SearchEngineDataObjectID" IN (' . $searchRecord->ListOfIDsCUSTOM . ')
                    AND "SearchEngineDataObjectID" NOT IN (' . implode(',', array_keys($array)) . ')
                    ' . $sortSQL . '
                ;';
            $rows = DB::query($sql);
            foreach ($rows as $row) {
                $id = $row['MyID'];
                if (! isset($array[$id])) {
                    $array[$id] = $row['RELEVANCE'];
                }
            }
            $ids = array_keys($array);

            //retrieve objects
            $objects = SearchEngineDataObject::get()
                ->filter(['ID' => $ids])
                ->sort('FIELD("ID", ' . implode(',', $ids) . ')');

            //group results!
            $objects = $this->makeClassGroups($objects);
        }

        return $objects;
    }
}
