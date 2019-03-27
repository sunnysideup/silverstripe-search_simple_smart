<?php

namespace Sunnysideup\SearchSimpleSmart\Sorters;

use SilverStripe\ORM\FieldType\DBDate;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use SilverStripe\CMS\Model\VirtualPage;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;

class SearchEngineSortByCreated extends SearchEngineSortByDescriptor
{

    /**
     * @return string
     */
    public function getShortTitle()
    {
        return _t("SearchEngineSortByCreated.TITLE", DBDate::class);
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
     * returns the sort statement that is addeded to search
     * query prior to searching the SearchEngineDataObjects
     *
     * return an array like
     *     Date => ASC
     *     Title => DESC
     *
     * @param mixed $sortProviderValues = null
     *
     * @return Array| null
     */
    public function getSqlSortArray($sortProviderValues = null)
    {
        return ["Created" => "DESC"];
    }


    /**
     *
     * @return boolean
     */
    public function hasCustomSort($sortProviderValues = null)
    {
        return true;
    }

    /**
     *
     * Does any custom filtering
     * @param SS_List $objects
     * @param SearchEngineSearchRecord $searchRecord
     *
     * @return SS_List
     */
    public function doCustomSort($objects, $searchRecord)
    {
        if ($objects->count() < 2) {
            //do nothing
        } else {
            $objects = $this->makeClassGroups($objects);
        }
        return $objects;
    }
}
