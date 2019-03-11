<?php

namespace Sunnysideup\SearchSimpleSmart\Sorters;

use SilverStripe\ORM\FieldType\DBDate;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use SilverStripe\CMS\Model\VirtualPage;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;

class SearchEngineSortByLastEdited extends SearchEngineSortByDescriptor
{

    /**
     * @return string
     */
    public function getShortTitle()
    {
        return _t("SearchEngineSortByLastEdited.TITLE", DBDate::class);
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
     * @param boolean $debug
     *
     * @return Array| null
     */
    public function getSqlSortArray($debug = false)
    {
        return array("LastEdited" => "DESC");
    }


    /**
     *
     * @return boolean
     */
    public function hasCustomSort()
    {
        return true;
    }

    /**
     *
     * Does any custom filtering
     * @param SS_List $objects
     * @param SearchEngineSearchRecord $searchRecord
     * @param boolean $debug
     *
     * @return SS_List
     */
    public function doCustomSort($objects, $searchRecord, $debug = false)
    {
        if ($objects->count() < 2) {
            //do nothing
        } else {
            if($this->hasClassGroups()) {
                $finalArray = $this->makeClassGroups(
                    $objects->Map("ID", "DataObjectClassName")->toArray(),
                    $debug
                );
                //retrieve objects
                $keys = array_keys($finalArray);
                //retrieve objects
                $objects = SearchEngineDataObject::get()
                    ->filter(array("ID" => $keys))
                    ->sort("FIELD(\"ID\", ".implode(",", $keys).")");
            }
        }
        return $objects;
    }
}
