<?php

namespace Sunnysideup\SearchSimpleSmart\Abstractions;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Extensible;

abstract class SearchEngineFilterForDescriptor
{


    use Extensible;
    use Injectable;
    use Configurable;

    /**
     * returns the name - e.g. "Pages Only", "Files Only"
     * @return String
     */
    abstract public function getShortTitle();

    /**
     * returns the description - e.g. "filter for pages about foo bar"
     * @return String
     */
    abstract public function getDescription();

    /**
     * list of
     * e.g.
     *    LARGE => Large Pages
     *    SMALL => Small Pages
     *    RED => Red Pages
     * @return Array
     */
    abstract public function getFilterList();

    /**
     * returns the sort statement that is addeded to search
     * query prior to searching the SearchEngineDataObjects
     * the filter array are the items selected by the
     * user, based on the filter options listed above
     * @see: getFilterList
     * return an array like
     *     "ClassName" => array("A", "B", "C"),
     *     "LastEdited:GreaterThan" => "10-10-2001"
     *
     * @param array $filterArray
     * @param boolean $debug
     *
     * @return array| null
     */
    abstract public function getSqlFilterArray($filterArray, $debug = false);

    /**
     * do we need to do custom filtering
     * the filter array are the items selected by the
     * user, based on the filter options listed above
     * @see: getFilterList
     * @param array $filterArray
     * @return boolean
     */
    abstract public function hasCustomFilter($filterArray);

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
    abstract public function doCustomFilter($objects, $searchRecord, $filterArray, $debug = false);

    /**
     * retains debug information if turned on.
     * @var array
     */
    protected $debugArray = [];

    /**
     * @return string (html)
     */
    public function getDebugArray()
    {
        return "<ul><li>".implode("</li>li><li>", $this->debugArray)."</li></ul>";
    }
}
