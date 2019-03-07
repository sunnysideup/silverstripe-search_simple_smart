<?php



class SearchEngineSortByDate extends SearchEngineSortByDescriptor
{

    /**
     * @return string
     */
    public function getShortTitle()
    {
        return _t("SearchEngineSortByDate.TITLE", "Date");
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
     * @param boolean $debug
     *
     * @return Array| null
     */
    public function getSqlSortArray($debug = false)
    {
        return array("LastEdited" => "DESC");
    }


    /**
     *
     * @return boolean
     */
    public function hasCustomSort()
    {
        return true;
    }

    /**
     *
     * Does any custom filtering
     * @param SS_List $objects
     * @param SearchEngineSearchRecord $searchRecord
     * @param boolean $debug
     *
     * @return SS_List
     */
    public function doCustomSort($objects, $searchRecord, $debug = false)
    {
        if ($objects->count() < 2) {
            //do nothing
        } else {
            $finalArray = $this->makeClassGroups(
                $objects->Map("ID", "DataObjectClassName")->toArray(),
                $debug
            );
            //retrieve objects
            $objects = SearchEngineDataObject::get()
                ->filter(array("ID" => array_keys($finalArray)))
                ->sort("FIELD(\"ID\", ".implode(",", array_keys($finalArray)).")");
        }
        return $objects;
    }
}
