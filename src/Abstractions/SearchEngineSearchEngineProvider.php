<?php

namespace Sunnysideup\SearchSimpleSmart\Abstractions;

use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;

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
     * @return return DataList
     */
    public function getRawResults();
}
