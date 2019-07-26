<?php

namespace Sunnysideup\SearchSimpleSmart\Sorters;

use SilverStripe\ORM\FieldType\DBDate;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;

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
     * returns the description - e.g. "sort by the last Edited date"
     * @return string
     */
    public function getDescription()
    {
        return $this->getShortTitle();
    }

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
    public function getSqlSortArray($sortProviderValues = null)
    {
        return ['LastEdited' => 'DESC'];
    }

    /**
     * @return boolean
     */
    public function hasCustomSort($sortProviderValues = null)
    {
        return true;
    }

    /**
     * Does any custom filtering
     * @param SS_List $objects
     * @param SearchEngineSearchRecord $searchRecord
     *
     * @return SS_List
     */
    public function doCustomSort($objects, $searchRecord)
    {
        return $this->makeClassGroups($objects);
    }
}
