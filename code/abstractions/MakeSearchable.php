<?php

/***
 * This is an interface that can be added
 * to any DataObject that is
 *
 *
 */

interface SearchEngineMakeSearchableInterface {

    /**
     * This array should look like this:
     *     array(
     *         1 => array("Title", "MetaTitle"),
     *         2 => array("Content"),
     *     );
     * where 1 and 2 are the levels of importance
     * You can use the following syntax:
     *   - DBField
     *   - DBField.Nice / DBField.Long / DBField.Raw / etc...
     *   - MyHasOne.DBField
     *   - MyHasOne.DBField.Nice / MyHasOne.DBField.Long / MyHasOne.DBField.Raw / etc...
     *   - MyHasOne.MySecondLevelHasOne
     *   - MyHasOne.MySecondLevelHasOne.DBField
     *   - MyHasOne.MySecondLevelHasOne.DBField.Nice / MyHasOne.MySecondLevelHasOne.DBField.Long / MyHasOne.MySecondLevelHasOne.DBField.Raw / etc...
     *
     * @var array
     */
    // private static $search_engine_full_contents_fields_array;

    /**
     * The Silvestripe Template used to show the result.
     * @var string
     */
    // private static $search_engine_results_templates;

    /**
     * Should this object type be excluded
     * @var boolean
     */
    // private static $search_engine_exclude_from_index;

    /**
     * returns a URL link to the object
     * @param string $action
     * @return string
     */
    public function Link($action = "");

    /**
     * returns a full-text version of an object like this:
     * array(
     *   1 => "bla",
     *   2 => "foo",
     *   3 => "bar"
     * );
     * where 1/2/3 are the levels of importance of each string.
     * You dont need to have 1,2, 3, you can also just return 1, 2 or just 1.
     * @return array
     */
    public function SearchEngineFullContentForIndexingProvider();

    /**
     * returns weigthing for each level of full content
     * array(
     *   1 => 30,
     *   2 => 20,
     *   3 => 10
     * );
     * where 1/2/3 are the levels of importance of each string.
     * You dont need to have 1,2, 3, you can also just return 1, 2 or just 1.
     * @return array
     */
    public function SearchEngineFullContentWeigthingProvider();

    /**
     * returns templates for formatting the object
     * in the search results.
     * @param boolean $moreDetails
     * @return string
     */
    public function SearchEngineResultsTemplatesProvider($moreDetails = false);

    /**
     * @see: MakeSearchable::search_engine_also_trigger
     * @return array
     */
    public function SearchEngineAlsoTriggerProvider();

    /**
     * return true if the object should not be indexed
     * @return boolean
     */
    public function SearchEngineExcludeFromIndexProvider();

}
