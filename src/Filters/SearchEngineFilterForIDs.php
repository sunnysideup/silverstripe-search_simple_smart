<?php

namespace Sunnysideup\SearchSimpleSmart\Filters;

use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineFilterForDescriptor;

class SearchEngineFilterForIDs extends SearchEngineFilterForDescriptor
{
    /**
     * @return string
     */
    public function getShortTitle()
    {
        return _t('SearchEngineFilterForIDs.TITLE', 'Custom Selection limited to one type');
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
     * list of
     * e.g.
     *    LARGE => Large Pages
     *    SMALL => Small Pages
     *    RED => Red Pages
     * @return array
     */
    public function getFilterList()
    {
        return ['CUSTOM_ONE_TYPE' => 'Selection for one type'];
    }

    /**
     * returns the filter statement that is addeded to search
     * query prior to searching the SearchEngineDataObjects
     *
     * return an array like
     *     "ClassName" => array("A", "B", "C"),
     *
     * @param array $filterArray
     *
     * @return array| null
     */
    public function getSqlFilterArray($filterArray)
    {
        return ['DataObjectID' => $filterArray];
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
}
