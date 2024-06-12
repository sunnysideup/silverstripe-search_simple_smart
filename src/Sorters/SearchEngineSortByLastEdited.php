<?php

namespace Sunnysideup\SearchSimpleSmart\Sorters;

use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\SS_List;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;

class SearchEngineSortByLastEdited extends SearchEngineSortByDescriptor
{
    /**
     * @return string
     */
    public function getShortTitle()
    {
        return _t('SearchEngineSortByLastEdited.TITLE', DBDate::class);
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
     * @return null|array
     */
    public function getSqlSortArray($sortProviderValues = null)
    {
        return ['LastEdited' => 'DESC'];
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
     * Does any custom filtering.
     *
     * @param SS_List|DataList         $objects
     * @param SearchEngineSearchRecord $searchRecord
     *
     * @return SS_List|DataList
     */
    public function doCustomSort($objects, $searchRecord)
    {
        return $this->makeClassGroups($objects);
    }
}
