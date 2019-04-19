<?php

namespace Sunnysideup\SearchSimpleSmart\Sorters;

use SilverStripe\Core\Convert;
use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;

/**
 * default sort option
 *
 *
 */

class SearchEngineSortByDate extends SearchEngineSortByDescriptor
{


    /**
     * @return string
     */
    public function getShortTitle()
    {
        return _t("SearchEngineSortByDate.TITLE", "Best Sortable Date");
    }

    /**
     * returns the description - e.g. "sort by the last Edited date"
     * @return String
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
     *
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

            //sort by DataObjectDate
            $sql = '
                SELECT
                    "SearchEngineDataObject"."ID" AS MyID,
                FROM "SearchEngineDataObject"
                WHERE
                    "SearchEngineDataObject"."ID" IN ('.$searchRecord->ListOfIDsCUSTOM.')
                ORDER BY
                    "DataObjectDate" DESC
                ;';
            $rows = DB::query($sql);
            $ids = [];
            foreach ($rows as $row) {
                $id = $row["MyID"];
                $ids[$id] = $id;
            }

            //retrieve objects
            $objects = SearchEngineDataObject::get()
                ->filter(["ID" => $ids])
                ->sort("FIELD(\"ID\", ".implode(",", $ids).")");

            //group results!
            $objects = $this->makeClassGroups($objects);
        }

        return $objects;
    }
}
