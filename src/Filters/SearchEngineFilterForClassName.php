<?php

namespace Sunnysideup\SearchSimpleSmart\Filters;

use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\ClassInfo;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineFilterForDescriptor;

class SearchEngineFilterForClassName extends SearchEngineFilterForDescriptor
{



    /**
     * list of classes that should be included if no values are set
     * @var array
     */
    private static $classes_to_include = [];

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
        $array = [];
        $filterArray = SearchEngineDataObject::searchable_class_names();
        $includeInGeneral = Config::inst()->get(SearchEngineDataObject::class, "classes_to_include");
        if (!is_array($includeInGeneral)) {
            $includeInGeneral = [];
        }
        $exclude = Config::inst()->get(SearchEngineDataObject::class, "classes_to_exclude");
        if (!is_array($exclude)) {
            $exclude = [];
        }
        $checkInclusion = false;
        $myInclusions = $this->Config()->get("classes_to_include");
        if (is_array($myInclusions) && count($myInclusions)) {
            $checkInclusion = true;
        }

        foreach ($filterArray as $className => $title) {
            if(count($includeInGeneral) === 0 || in_array($className, $includeInGeneral)) {
                if (! in_array($className, $exclude)) {
                    if ($checkInclusion) {
                        if (in_array($className, $myInclusions)) {
                            $array[$className] = $title;
                        } elseif (isset($myInclusions[$className])) {
                            $array[$className] = $myInclusions[$className];
                        }
                    } else {
                        $array[$className] = $title;
                    }
                }
            }
        }

        return $array;
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
        if(! $filterArray) {
            $filterArray = array_keys($this->getFilterList());
        }
        $array = [];

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

}
