<?php



class SearchEngineFilterForRecent extends SearchEngineFilterForDescriptor
{

    /**
     * @return String
     */
    public function getShortTitle()
    {
        return _t("SearchEngineFilterForPage.TITLE", "Recent");
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
     * list of
     * e.g.
     *    LARGE => Large Pages
     *    SMALL => Small Pages
     *    RED => Red Pages
     * @return Array
     */
    public function getFilterList()
    {
        return array("WEEK" => "Updated in last week");
    }

    /**
     * returns the sort statement that is addeded to search
     * query prior to searching the SearchEngineDataObjects
     *
     * return an array like
     *     Date => ASC
     *     Title => DESC
     * @param array $filterArray
     * @param boolean $debug
     *
     * @return Array| null
     */
    public function getSqlFilterArray($filterArray, $debug = false)
    {
        return array("LastEdited:GreaterThan" => date("Y-m-d", strtotime("-1 week")));
    }

    /**
     * do we need to do custom filtering
     * the filter array are the items selected by the
     * user, based on the filter options listed above
     * @see: getFilterList
     * @param array $filterArray
     * @return boolean
     */
    public function hasCustomFilter($filterArray)
    {
        return false;
    }

    /**
     *
     * Does any custom filtering
     * @param SS_List $objects
     * @param SearchEngineSearchRecord $searchRecord
     * @param array $filterArray
     * @param boolean $debug
     *
     * @return SS_List
     */
    public function doCustomFilter($objects, $searchRecord, $filterArray, $debug = false)
    {
        return $objects;
    }
}
