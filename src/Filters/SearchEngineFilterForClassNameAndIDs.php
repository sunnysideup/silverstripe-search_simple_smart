<?php

namespace Sunnysideup\SearchSimpleSmart\Filters;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\SS_List;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineFilterForDescriptor;
use Sunnysideup\SearchSimpleSmart\Api\FasterIDLists;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;

class SearchEngineFilterForClassNameAndIDs extends SearchEngineFilterForDescriptor
{
    /**
     * @return string
     */
    public function getShortTitle()
    {
        return _t('SearchEngineFilterForClassNameAndIDs.TITLE', 'Seleted Items from Type');
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
        return ['TYPE' => 'Selection of items'];
    }

    /**
     * returns the filter statement that is addeded to search
     * query prior to searching the SearchEngineDataObjects
     *
     * return an array like
     *     "ClassName" => array("A", "B", "C"),
     *     "LastEdited:GreaterThan" => "10-10-2001"
     *
     * @param array|SS_List|null $filterArray
     *
     * @return array|null
     */
    public function getSqlFilterArray($filterArray): ?array
    {
        if (! $filterArray) {
            return null;
        } elseif (is_array($filterArray) && count($filterArray) === 0) {
            return null;
        } elseif ($filterArray instanceof SS_List) {
            $ids = $filterArray->column('ID');
            $classNames = $filterArray->column('ClassName');
            $preFilter = [];
            foreach ($ids as $position => $id) {
                $className = $classNames[$position];
                if (! isset($preFilter[$className])) {
                    $preFilter[$className] = [];
                }
                $preFilter[$className][$id] = $id;
            }
            return $this->getSqlFilterArray($preFilter);
        }
        $array = [];

        foreach ($filterArray as $className => $ids) {
            $classNames = ClassInfo::subclassesFor($className);
            $dataList = Injector::inst()->create(
                FasterIDLists::class,
                SearchEngineDataObject::class,
                $ids,
                'DataObjectID'
            )->filteredDatalist();

            // $dataList = SearchEngineDataObject::get()
            //     ->filter(['DataObjectClassName' => $classNames, 'DataObjectID' => $ids]);
            $dataList = $dataList
                ->filter(['DataObjectClassName' => $classNames]);
            $array = array_merge($array, $dataList->column('ID'));
        }
        $array = array_unique($array);

        return ['ID' => $array];
    }

    /**
     * do we need to do custom filtering
     * the filter array are the items selected by the
     * user, based on the filter options listed above
     * @see: getFilterList
     * @param array|SS_List|null $filterArray
     * @return boolean
     */
    public function hasCustomFilter($filterArray): bool
    {
        return false;
    }
}
