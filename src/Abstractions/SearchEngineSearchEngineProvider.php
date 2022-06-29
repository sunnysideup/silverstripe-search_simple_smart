<?php

namespace Sunnysideup\SearchSimpleSmart\Abstractions;

use SilverStripe\ORM\DataList;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;

/*
 * Interface for the class that returns the matching
 * SearchEngineDataObjects before filtering
 * and sorting takes place.
 *
 */

interface SearchEngineSearchEngineProvider
{
    public function setSearchRecord(SearchEngineSearchRecord $searchRecord);

    /**
     * @return DataList
     */
    public function getRawResults();
}
