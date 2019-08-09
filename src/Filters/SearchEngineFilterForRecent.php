<?php

namespace Sunnysideup\SearchSimpleSmart\Filters;

use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineFilterForDescriptor;

class SearchEngineFilterForRecent extends SearchEngineFilterForDescriptor
{
    private static $recent_string = '-1 week';

    /**
     * @return string
     */
    public function getShortTitle()
    {
        return _t('SearchEngineFilterForPage.TITLE', 'Recent');
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
        return ['WEEK' => 'Updated in last week'];
    }

    /**
     * returns the sort statement that is addeded to search
     * query prior to searching the SearchEngineDataObjects
     *
     * return an array like
     *     Date => ASC
     *     Title => DESC
     * @param array|SS_List|null $filterArray
     *
     * @return array|null
     */
    public function getSqlFilterArray($filterArray):?array
    {
        $recentTS = strtotime($this->Config()->get('recent_string'));

        return ['LastEdited:GreaterThan' => date('Y-m-d', $recentTS)];
    }

    /**
     * do we need to do custom filtering
     * the filter array are the items selected by the
     * user, based on the filter options listed above
     * @see: getFilterList
     * @param array|SS_List|null $filterArray
     * @return boolean
     */
    public function hasCustomFilter($filterArray)
    {
        return false;
    }
}
