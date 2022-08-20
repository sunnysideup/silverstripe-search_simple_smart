<?php

namespace Sunnysideup\SearchSimpleSmart\Sorters;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\DataList;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;
use Sunnysideup\SearchSimpleSmart\Api\FasterIDLists;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;

/**
 * default sort option.
 */
class SearchEngineSortByDate extends SearchEngineSortByDescriptor
{
    /**
     * @return string
     */
    public function getShortTitle()
    {
        return _t('SearchEngineSortByDate.TITLE', 'Best Sortable Date');
    }

    /**
     * returns the description - e.g. "sort by the last Edited date".
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->getShortTitle();
    }

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
     * @return array
     */
    public function getSqlSortArray($sortProviderValues = null)
    {
        return [];
    }

    /**
     * @param null|mixed $sortProviderValues
     *
     * @return bool
     */
    public function hasCustomSort($sortProviderValues = null)
    {
        return true;
    }

    /**
     * Do any custom sorting.
     *
     * @param SS_List|DataList         $objects
     * @param SearchEngineSearchRecord $searchRecord
     *
     * @return SS_List|DataList
     */
    public function doCustomSort($objects, $searchRecord)
    {
        if ($objects->count() < 2) {
            //do nothing
        } else {
            //retrieve objects
            $objects = Injector::inst()->create(
                FasterIDLists::class,
                SearchEngineDataObject::class,
                explode(',', $searchRecord->ListOfIDsCUSTOM)
            )->filteredDatalist();

            $objects = $objects
                ->sort(['DataObjectDate' => 'DESC'])
            ;
            // $objects = SearchEngineDataObject::get()
            //     ->filter(['ID' => explode(',', $searchRecord->ListOfIDsCUSTOM)])
            //     ->sort(['DataObjectDate' => 'DESC']);

            //group results!
            $objects = $this->makeClassGroups($objects);
        }

        return $objects;
    }
}
