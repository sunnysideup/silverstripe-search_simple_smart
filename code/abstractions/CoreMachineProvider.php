<?php

/***
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
     *
     * @var string
     */
    private static $class_name_for_search_provision;


    /**
     * this function runs the Core Search Machine
     * @param string $searchPhrase
     * @param array $filterProviders
     * @param string $sortProvider
     *
     * @return DataList
     */
    public function run($searchPhrase, $filterProviders = array(), $sortProvider = "");

    /**
     * returns HTML for Debug
     * @return string
     */
    public function getDebugString();
}
