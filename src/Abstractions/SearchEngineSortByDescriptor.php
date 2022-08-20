<?php

namespace Sunnysideup\SearchSimpleSmart\Abstractions;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\SS_List;
use Sunnysideup\SearchSimpleSmart\Api\FasterIDLists;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;

abstract class SearchEngineSortByDescriptor
{
    use Extensible;
    use Injectable;
    use Configurable;

    protected $debug = false;

    /**
     * retains debug information if turned on.
     *
     * @var array
     */
    protected $debugArray = [];

    /**
     * this is a metasorter, allowing you to always
     * put some classes on top or at the bottom
     * e.g. always put Product Pages at the top.
     * array is like this:
     *    1 => array(MyFirstClassName, MySecondClassName)
     *    2 => array(MyOtherClassName, MyFooBarClassName).
     *
     * @var array
     */
    private static $class_groups = [];

    /**
     * set the total number of results per class_group
     * e.g.
     * 1 => 12
     * 2 => 5.
     *
     * @var array
     */
    private static $class_group_limits = [];

    public function __construct($debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * returns the name - e.g. "Date", "Relevance".
     *
     * @return string
     */
    abstract public function getShortTitle();

    /**
     * returns the description - e.g. "sort by the last Edited date".
     *
     * @return string
     */
    abstract public function getDescription();

    /**
     * returns the sort statement that is addeded to search
     * query prior to searching the SearchEngineDataObjects.
     *
     * return an array like
     *     Date => ASC
     *     Title => DESC
     *
     * @param mixed $sortProviderValues
     *
     * @return null|array
     */
    abstract public function getSqlSortArray($sortProviderValues = null);

    /**
     * Do we need to do custom sorting?
     *
     * @param null|mixed $sortProviderValues
     *
     * @return bool
     */
    public function hasCustomSort($sortProviderValues = null)
    {
        $array = $this->getSqlSortArray($sortProviderValues);

        return ! (is_array($array) && [] !== $array);
    }

    /**
     * Do any custom sorting.
     *
     * @param SS_List $objects      - id => ClassName
     * @param mixed $searchRecord
     *
     * @return DataList|SS_List
     */
    abstract public function doCustomSort($objects, $searchRecord);

    /**
     * @return string (html)
     */
    public function getDebugArray()
    {
        return '<ul><li>' . implode('</li>li><li>', $this->debugArray) . '</li></ul>';
    }

    // protected function groupCustomSort($objects)
    // {
    //     if ($this->hasClassGroups()) {
    //         //retrieve objects
    //         $keys = array_keys($finalArray);
    //     }
    //     return $objects;
    // }

    /**
     * @param DataList $objects
     *
     * @return DataList
     */
    protected function makeClassGroups($objects)
    {
        if ($this->hasClassGroups()) {
            if ($objects->count() > 1) {
                $classGroupCounts = [];
                $classGroups = Config::inst()->get(self::class, 'class_groups');
                $classGroupLimits = Config::inst()->get(self::class, 'class_group_limits');
                $newArray = [];
                $array = $objects->Map('ID', 'DataObjectClassName')->toArray();
                foreach ($classGroups as $key => $classGroupGroup) {
                    if (! isset($classGroupCounts[$key])) {
                        $classGroupCounts[$key] = 0;
                    }

                    foreach ($array as $id => $className) {
                        if (in_array($className, $classGroupGroup, true)) {
                            if (! isset($classGroupLimits[$key]) || (isset($classGroupLimits[$key]) && ($classGroupCounts[$key] <= $classGroupLimits[$key]))) {
                                ++$classGroupCounts[$key];
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
                $objects = Injector::inst()->create(
                    FasterIDLists::class,
                    SearchEngineDataObject::class,
                    $keys
                )->filteredDatalist();

                $objects = $objects->sort('FIELD("ID", ' . implode(',', $keys) . ')');
                // $objects = SearchEngineDataObject::get()
                //     ->filter(['ID' => $keys])
                //     ->sort('FIELD("ID", ' . implode(',', $keys) . ')');
            }
        }

        return $objects;
    }

    protected function hasClassGroups()
    {
        $classGroups = Config::inst()->get(self::class, 'class_groups');

        return is_array($classGroups) && count($classGroups);
    }

    protected function hasNoClassGroups()
    {
        return ! (bool) $this->hasClassGroups();
    }
}
