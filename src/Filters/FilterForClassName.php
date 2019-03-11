<?php

namespace Sunnysideup\SearchSimpleSmart\Filters;

use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\ClassInfo;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineFilterForDescriptor;

class SearchEngineFilterForClassName extends SearchEngineFilterForDescriptor
{

    /**
     * list of classes that should be "filterable"
     * @var array
     */
    private static $classes_to_include = array();

    /**
     * @return String
     */
    public function getShortTitle()
    {
        return _t("SearchEngineFilterForClassName.TITLE", "Type");
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
        $array = array();
        $filterArray = SearchEngineDataObject::searchable_class_names();
        $exclude = Config::inst()->get(SearchEngineDataObject::class, "classes_to_exclude");
        if (!is_array($exclude)) {
            $exclude = array();
        }
        $checkInclusion = false;
        $include = $this->Config()->get("classes_to_include");
        if (is_array($include) && count($include)) {
            $checkInclusion = true;
        }

        foreach ($filterArray as $className => $title) {
            if (!in_array($className, $exclude)) {
                if ($checkInclusion) {
                    if (in_array($className, $include)) {
                        $array[$className] = $title;
                    } elseif (isset($inclusion[$className])) {
                        $array[$className] = $inclusion[$className];
                    }
                } else {
                    $array[$className] = $title;
                }
            }
        }
        return $array;
    }

    /**
     * returns the sort statement that is addeded to search
     * query prior to searching the SearchEngineDataObjects
     *
     * return an array like
     *     "ClassName" => array("A", "B", "C"),
     *     "LastEdited:GreaterThan" => "10-10-2001"
     *
     * @param array $filterArray
     * @param boolean $debug
     *
     * @return array| null
     */
    public function getSqlFilterArray($filterArray, $debug = false)
    {
        $array = array();

        foreach ($filterArray as $className) {
            $array += ClassInfo::subclassesFor($className);
        }
        return array("DataObjectClassName" => $array);
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
