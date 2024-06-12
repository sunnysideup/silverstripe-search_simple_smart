<?php

namespace Sunnysideup\SearchSimpleSmart\Abstractions;

use SilverStripe\ORM\DataList;

/*
 * Interface for the class that returns
 * the sorted and filtered SearchEngineDataObjects
 *
 */

interface SearchEngineCoreMachineProvider
{
    /**
     * class used to provide the raw results
     * raw results are the SearchEngineDataObject matches for a particular keyword
     * phrase, without any filters or sort.
     * This is the base collection.
     */
    // private static $class_name_for_search_provision;

    /**
     * this function runs the Core Search Machine.
     *
     * @return DataList
     */
    public function run(?string $searchPhrase = '', ?array $filterProviders = [], ?string $sortProvider = '', $sortProviderValues = null);

    /**
     * returns HTML for Debug.
     *
     * @return string
     */
    public function getDebugString();
}
