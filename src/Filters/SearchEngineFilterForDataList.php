<?php

namespace Sunnysideup\SearchSimpleSmart\Filters;

use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\SS_List;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineFilterForDescriptor;

class SearchEngineFilterForDataList extends SearchEngineFilterForClassNameAndIDs
{

    /**
     * @return String
     */
    public function getShortTitle()
    {
        return _t("SearchEngineFilterForDataList.TITLE", "Custom Selection");
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
        return array("CUSTOM" => "Custom selection of items");
    }

    /**
     * returns the filter statement that is addeded to search
     * query prior to searching the SearchEngineDataObjects
     *
     * return an array like
     *     "ClassName" => array("A", "B", "C"),
     *     "LastEdited:GreaterThan" => "10-10-2001"
     *
     * @param SS_List|null $list
     *
     * @return array|null
     */
    public function getSqlFilterArray($list)
    {
        if($filterArray instanceof SS_List) {
            if($filterArray->count() === 0){
                return ['ID' => -1];
            }
            $ids = $list->column('ID');
            $classNames = $list->column('ClassName');
            $preFilter = [];
            foreach($ids as $position => $id) {
                $className = $classNames[$position];
                if(! isset($preFilter[$className])) {
                    $preFilter[$className] = [];
                }
                $preFilter[$className][$id] = $id;
            }
            return parent::getSqlFilterArray($preFilter);
        }

        return null;
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
