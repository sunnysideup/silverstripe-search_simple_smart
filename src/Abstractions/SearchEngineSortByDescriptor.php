<?php

namespace Sunnysideup\SearchSimpleSmart\Abstractions;

use SilverStripe\Core\Config\Config;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Extensible;

abstract class SearchEngineSortByDescriptor
{

    use Extensible;
    use Injectable;
    use Configurable;

    /**
     * this is a metasorter, allowing you to always
     * put some classes on top or at the bottom
     * e.g. always put Product Pages at the top.
     * array is like this:
     *    1 => array(MyFirstClassName, MySecondClassName)
     *    2 => array(MyOtherClassName, MyFooBarClassName)
     *
     * @var array
     */
    private static $class_groups = [];

    /**
     * set the total number of results per class_group
     * e.g.
     * 1 => 12
     * 2 => 5
     *
     * @var array
     */
    private static $class_group_limits = [];

    /**
     * returns the name - e.g. "Date", "Relevance"
     * @return String
     */
    abstract public function getShortTitle();

    /**
     * returns the description - e.g. "sort by the last Edited date"
     * @return String
     */
    abstract public function getDescription();

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
     * @return array|null
     */
    abstract public function getSqlSortArray($sortProviderValues = null);


    protected $debug = false;

    public function __construct($debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Do we need to do custom sorting?
     * @return boolean
     */
    public function hasCustomSort($sortProviderValues = null)
    {
        $array = $this->getSqlSortArray($sortProviderValues);
        if (is_array($array) && count($array) > 0) {
            return false;
        }
        return true;
    }

    /**
     * Do any custom sorting
     *
     *
     * @param array $array - id => ClassName
     * @return SS_List
     */
    abstract public function doCustomSort($objects, $searchRecord);

    protected function groupCustomSort($objects) {
        if($this->hasClassGroups()) {
            //retrieve objects
            $keys = array_keys($finalArray);

        }
        return $objects;
    }

    /**
     *
     * @param  [type] $array an array if IDs,
     * @return [type]        [description]
     */
    protected function makeClassGroups($objects)
    {
        if($this->hasClassGroups()) {
            if ($objects->count() > 1) {
                $classGroupCounts = [];
                $classGroups = Config::inst()->get(SearchEngineSortByDescriptor::class, "class_groups");
                $classGroupLimits = Config::inst()->get(SearchEngineSortByDescriptor::class, "class_group_limits");
                $newArray = [];
                $array = $objects->Map("ID", "DataObjectClassName")->toArray();
                foreach ($classGroups as $key => $classGroupGroup) {
                    if (!isset($classGroupCounts[$key])) {
                        $classGroupCounts[$key] = 0;
                    }
                    foreach ($array as $id => $className) {
                        if (in_array($className, $classGroupGroup)) {
                            if ((!isset($classGroupLimits[$key]))  || (isset($classGroupLimits[$key]) && ($classGroupCounts[$key] <= $classGroupLimits[$key]))) {
                                $classGroupCounts[$key]++;
                                $newArray[$id] = $className;
                            }
                            unset($array[$id]);
                        }
                    }
                }

                foreach ($array as $id => $className) {
                    $newArray[$id] = $className;
                }
                $keys = array_keys($newArray);
                //retrieve objects
                $objects = SearchEngineDataObject::get()
                    ->filter(array("ID" => $keys))
                    ->sort("FIELD(\"ID\", ".implode(",", $keys).")");
            }
        }
        return $objects;
    }

    protected function hasClassGroups()
    {
        $classGroups = Config::inst()->get(SearchEngineSortByDescriptor::class, "class_groups");

        return is_array($classGroups) && count($classGroups) ? true : false;
    }

    protected function hasNoClassGroups()
    {
        return $this->hasClassGroups() ? false : true;
    }

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
