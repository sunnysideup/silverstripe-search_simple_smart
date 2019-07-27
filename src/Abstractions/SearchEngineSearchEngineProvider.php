<?php

namespace Sunnysideup\SearchSimpleSmart\Abstractions;

use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;
use SilverStripe\ORM\DataList;

/***
 * Interface for the class that returns the matching
 * SearchEngineDataObjects before filtering
 * and sorting takes place.
 *
 */

interface SearchEngineSearchEngineProvider
{
    /**
     * @param SearchEngineSearchRecord $searchRecord
     */
    public function setSearchRecord(SearchEngineSearchRecord $searchRecord);

    /**
     * @return DataList
     */
    public function getRawResults();
}
