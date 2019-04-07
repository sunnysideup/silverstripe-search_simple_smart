<?php

namespace Sunnysideup\SearchSimpleSmart\Extensions;

use SilverStripe\Control\Email\Email;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;
use SilverStripe\Versioned\Versioned;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineFullContent;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Security\Permission;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DB;
use SilverStripe\CMS\Model\SiteTree;
use Sunnysideup\SearchSimpleSmart\Extensions\SearchEngineMakeSearchable;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;
use Sunnysideup\SearchSimpleSmart\Api\ExportKeywordList;
use SilverStripe\ORM\DataExtension;

/**
 * Add this DataExtension to any object that you would like to make
 * searchable.
 *
 *
 *
 *
 */


class SearchEngineMakeSearchable extends DataExtension
{











    ############################
    # do stuff ....
    ############################

    /**
     * deletes cached search results
     * sets stage to LIVE
     * indexes the current object.
     * @param $searchEngineDataObject SearchEngineDataObject
     * @param $withModeChange do everything necessary for indexings.
     *                        Setting this to false means the stage will not be set
     *                        and the cache will not be cleared.
     */
    public function doSearchEngineIndex($searchEngineDataObject = null, $withModeChange = true)
    {
        //last check...
        if(! $searchEngineDataObject) {
            $searchEngineDataObject = SearchEngineDataObject::find_or_make($this->owner);
        } else {
            if ($this->SearchEngineExcludeFromIndex()) {
                $searchEngineDataObject = null;
            }
            //do nothing
        }
        if($searchEngineDataObject) {
            $searchEngineDataObject->doSearchEngineIndex($this->owner, $withModeChange);
        }
    }



    /**
     * returns a full-text version of an object like this:
     * array(
     *   1 => 'bla',
     *   2 => 'foo',
     * );
     * where 1 and 2 are the levels of importance of each string.
     *
     * @return array
     */
    public function SearchEngineFullContentForIndexingBuild($searchEngineDataObject = null)
    {
        if(! $searchEngineDataObject) {
            $searchEngineDataObject = SearchEngineDataObject::find_or_make($this->owner);
        }
        if($searchEngineDataObject) {
            return $searchEngineDataObject->SearchEngineFullContentForIndexingBuild($this->owner);
        }
    }



    /**
     * returns a list of classnames + IDs
     * that also need to be updated when this object is updated:
     *     return array(
     *          [0] => array('ClassName' => MyOtherClassname, 'ID' => 123),
     *          [1] => array('ClassName' => FooClassName, 'ID' => 122)
     *          [1] => array('ClassName' => BarClassName, 'ID' => 124)
     *      )
     * @return array
     */
    public function SearchEngineAlsoTrigger()
    {
        if ($this->owner->hasMethod('SearchEngineAlsoTriggerProvider')) {
            return $this->owner->SearchEngineAlsoTriggerProvider();
        }
        return [];
    }









    ############################
    # searches
    ############################

    /**
     * Indexed Keywords
     * @return DataList
     */
    public function SearchEngineKeywordDataObjectMatches($level = 1)
    {
        $item = SearchEngineDataObject::find_or_make($this->owner);
        if($item) {
            $field = 'SearchEngineKeywords_Level'.$level;

            return $item->$field();
        } else {

            return SearchEngineKeyword::get()->filter(['ID' => 0]);
        }
    }













    ############################
    # display....
    ############################

    /**
     * returns a template for formatting the object
     * in the search results.
     *
     * @param boolean $moreDetails
     *
     * @return array
     */
    public function SearchEngineResultsTemplates($moreDetails = false)
    {
        $item = SearchEngineDataObject::find_or_make($this->owner);
        if($item) {
            $item->SearchEngineResultsTemplates($this->owner, $moreDetails);
        }
    }










    ############################
    # CMS
    ############################

    public function updateCMSFields(FieldList $fields)
    {
        if (SiteConfig::current_site_config()->SearchEngineDebug || Permission::check('SEARCH_ENGINE_ADMIN')) {
            if ($fields->fieldByName('Root')) {
                $fields->findOrMakeTab('Root.Main');
                $fields->removeFieldFromTab('Root.Main', 'SearchEngineDataObjectID');
                if (!$this->owner->hasMethod('getSettingsFields')) {
                    return $this->updateSettingsFields($fields);
                }
            }
        }
    }

    public function updateSettingsFields(FieldList $fields)
    {
        if (SiteConfig::current_site_config()->SearchEngineDebug || Permission::check('SEARCH_ENGINE_ADMIN')) {
            if ($this->SearchEngineExcludeFromIndex()) {
                return;
            } else {
                $item = SearchEngineDataObject::find_or_make($this->owner);
                $toBeIndexed = SearchEngineDataObjectToBeIndexed::get()->filter(['SearchEngineDataObjectID' => $item->ID, 'Completed' => 0])->count() ? 'yes' : 'no';
                $hasBeenIndexed = $this->SearchEngineIsIndexed() ? 'yes' : 'no';
                $fields->addFieldToTab('Root.SearchEngine', ReadonlyField::create('LastIndexed', 'Approximately Last Index', $this->owner->LastEdited));
                $fields->addFieldToTab('Root.SearchEngine', ReadonlyField::create('ToBeIndexed', 'On the list to be indexed', $toBeIndexed));
                $fields->addFieldToTab('Root.SearchEngine', ReadonlyField::create('HasBeenIndexed', 'Has been indexed', $hasBeenIndexed));
                $config = GridFieldConfig_RecordEditor::create()->removeComponentsByType(GridFieldAddNewButton::class);
                $fields->addFieldToTab(
                    'Root.SearchEngine',
                    $itemField = new LiteralField(
                        'Levels',
                        $this->owner->SearchEngineFieldsToBeIndexedHumanReadable(true)
                    )
                );
                $fields->addFieldToTab(
                    'Root.SearchEngine',
                    new GridField(
                        'SearchEngineKeywords_Level1',
                        'Keywords Level 1',
                        $this->owner->SearchEngineKeywordDataObjectMatches(1),
                        $config
                    )
                );
                $fields->addFieldToTab(
                    'Root.SearchEngine',
                    new GridField(
                        'SearchEngineKeywords_Level2',
                        'Keywords Level 2',
                        $this->owner->SearchEngineKeywordDataObjectMatches(2),
                        $config
                    )
                );
                $fields->addFieldToTab(
                    'Root.SearchEngine',
                    new GridField(
                        'SearchEngineFullContent',
                        'Full Content',
                        $this->owner->SearchEngineDataObjectFullContent(),
                        $config
                    )
                );
                $fields->addFieldToTab(
                    'Root.SearchEngine',
                    $itemField = new GridField(
                        'SearchEngineDataObject',
                        'Searchable Item',
                        SearchEngineDataObject::get()->filter(array('DataObjectClassName' => $this->owner->ClassName, 'DataObjectID' => $this->owner->ID)),
                        $config
                    )
                );
            }
        }
    }

    public function SearchEngineFieldsToBeIndexedHumanReadable($includeExample = false)
    {
        $item = SearchEngineDataObject::find_or_make($this->owner);
        if($item) {
            return $item->SearchEngineFieldsToBeIndexedHumanReadable($this->owner, $includeExample);
        }
    }













    ############################
    # on Before And After CRUD ...
    ############################



    public function onAfterPublish()
    {
        $this->onAfterWrite();
        //todo: make sure that the linked objects are also reindexed.
    }

    /**
     *
     * also delete SearchEngineDataObject
     * and all that relates to it.
     */
    public function onBeforeDelete()
    {
        $this->SearchEngineDeleteFromIndexing();
        //todo: make sure that the linked objects are also reindexed.
    }

    /**
     *
     * delete SearchEngineDataObject
     * and all that relates to it.
     */
    public function onAfterUnpublish()
    {
        $this->SearchEngineDeleteFromIndexing();
        //todo: make sure that the linked objects are also reindexed.
    }

    /**
     *
     * Mark for update
     */
    public function onBeforeWrite()
    {
        $alsoTrigger = $this->owner->SearchEngineAlsoTrigger();
        if (is_array($alsoTrigger) && count($alsoTrigger)) {
            foreach ($alsoTrigger as $details) {
                $className = $details['ClassName'];
                $id = $details['ID'];
                $obj = $className::get()->byID($id);
                if($obj->hasExtension(Versioned::class)) {
                    $doPublish = $obj->isPublished();
                    $obj->writeToStage(Versioned::DRAFT);
                    if($doPublish) {
                        $obj->publish(Versioned::DRAFT, Versioned::LIVE);
                    }
                } else {
                    $obj->write();
                }
            }
        }
    }

    /**
     * @var int
     */
    private $_onAfterWriteCount = [];

    /**
     *
     * Mark for update
     */
    public function onAfterWrite()
    {
        if ($this->SearchEngineExcludeFromIndex()) {
            //do nothing...
        } else {
            if(!isset($this->_onAfterWriteCount[$this->owner->ID])) {
                $this->_onAfterWriteCount[$this->owner->ID] = 0;
            }
            $item = SearchEngineDataObject::find_or_make($this->owner);
            $this->_onAfterWriteCount[$this->owner->ID]++;
            if ($item && $this->_onAfterWriteCount[$this->owner->ID] < 2) {
                ExportKeywordList::export_keyword_list();
                // $item->write();
                // DB::query('UPDATE \'SearchEngineDataObject\' SET LastEdited = NOW() WHERE ID = '.(intval($item->ID)-0).';');
                SearchEngineDataObjectToBeIndexed::add($item);
            }
        }
    }


    public function SearchEngineDeleteFromIndexing()
    {
        if ($item = SearchEngineDataObject::find_or_make($this->owner, $doNotMake = true)) {
            if ($item && $item->exists()) {
                $item->delete();
            }
        }
    }












    #####################################
    # get index data ...
    #####################################

    /**
     * returns array like this:
     * 1 => array('Title', 'MenuTitle')
     * 2 => array('Content')
     * @return array
     */
    public function SearchEngineFieldsForIndexing()
    {
        $item = SearchEngineDataObject::find_or_make($this->owner);
        if($item) {

            return $item->SearchEngineFieldsForIndexing($this->owner);
        }
        return [
            1 => [],
            2 => []
        ];
    }

    /**
     * Indexed Full Content Data
     * @return DataList
     */
    public function SearchEngineDataObjectFullContent()
    {
        $item = SearchEngineDataObject::find_or_make($this->owner);
        if($item) {

            return $item->SearchEngineFullContents();
        } else {

            return SearchEngineFullContent::get()->filter(['ID' =>  0]);
        }
    }

    /**
     * @param DataObject $object
     * @param array $fields array of TWO items.  The first specifies the relation,
     *                      the second one the method that should be run on the relation (if any)
     *                      you can also specific more relations ...
     *
     * @return array
     */
    public function SearchEngineRelObject($object, $fields, $str = '')
    {
        if (count($fields) == 0 || !is_array($fields)) {
            $str .= ' '.$object->getTitle().' ';
        } else {
            $fieldCount = count($fields);
            $possibleMethod = $fields[0];
            if(substr($possibleMethod, 0, 3) === 'get' &&  $object->hasMethod($possibleMethod) && $fieldCount == 1) {
                $str .= ' '.$object->$possibleMethod().' ';
            } else {
                $dbArray = $this->SearchEngineRelFields($object, 'db');
                //db field
                if (isset($dbArray[$fields[0]])) {
                    $dbField = $fields[0];
                    if ($fieldCount == 1) {
                        $str .= ' '.$object->$dbField.' ';
                    } elseif ($fieldCount == 2) {
                        $method = $fields[1];
                        $str .= ' '.$object->dbObject($dbField)->$method().' ';
                    }
                }
                //has one relation
                else {
                    $method = array_shift($fields);
                    $hasOneArray = array_merge(
                        $this->SearchEngineRelFields($object, 'has_one'),
                        $this->SearchEngineRelFields($object, 'belongs_to')
                    );
                    //has_one relation
                    if (isset($hasOneArray[$method])) {
                        $foreignObject = $object->$method();
                        $str .= ' '.$this->searchEngineRelObject($foreignObject, $fields).' ';
                    }
                    //many relation
                    else {
                        $manyArray = array_merge(
                            $this->SearchEngineRelFields($object, 'has_many'),
                            $this->SearchEngineRelFields($object, 'many_many'),
                            $this->SearchEngineRelFields($object, 'belongs_many_many')
                        );
                        if (isset($manyArray[$method])) {
                            $foreignObjects = $object->$method()->limit(100);
                            foreach ($foreignObjects as $foreignObject) {
                                $str .= ' '.$this->searchEngineRelObject($foreignObject, $fields).' ';
                            }
                        }
                    }
                }
            }
        }

        return $str;
    }

    /**
     *
     * @var array
     */
    private $_array_of_relations = [];

    /**
     * returns db, has_one, has_many, many_many, or belongs_many_many fields
     * for object
     *
     * @param DataObject $object
     * @param string $relType (db, has_one, has_many, many_many, or belongs_many_many)
     *
     * @return string
     */
    public function SearchEngineRelFields($object, $relType)
    {
        if (!isset($this->_array_of_relations[$object->ClassName])) {
            $this->_array_of_relations[$object->ClassName] = [];
        }
        if (!isset($this->_array_of_relations[$object->ClassName][$relType])) {
            $this->_array_of_relations[$object->ClassName][$relType] = Config::inst()->get($object->ClassName, $relType);
        }
        return $this->_array_of_relations[$object->ClassName][$relType];
    }











    ######################################
    # Status
    ######################################


    /**
     * Is this object indexed?
     * @return bool
     */
    public function SearchEngineIsIndexed()
    {
        $item = SearchEngineDataObject::find_or_make($this->owner, false);
        if($item && $item->exists()) {

            return true;
        } else {

            return false;
        }
    }

    private static $_search_engine_exclude_from_index = [];
    private static $_search_engine_exclude_from_index_per_class = [];

    /**
     * @return boolean
     */
    public function SearchEngineExcludeFromIndex()
    {
        $key = $this->owner->ClassName.'_'.$this->owner->ID;
        if(! isset(self::$_search_engine_exclude_from_index[$key])) {
            if(! isset(self::$_search_engine_exclude_from_index_per_class[$this->owner->ClassName])) {
                $exclude = false;
                $alwaysExcludeClassNames = Config::inst()->get(SearchEngineDataObject::class, 'classes_to_exclude');
                foreach($alwaysExcludeClassNames as $alwaysExcludeClassName) {
                    if (
                        $this->owner->ClassName === $alwaysExcludeClassName ||
                        is_subclass_of($this->owner->ClassName, $alwaysExcludeClassName)
                    ) {
                        $exclude = true;
                    }
                }
                self::$_search_engine_exclude_from_index_per_class[$this->owner->ClassName] = $exclude;
            }
            $exclude = self::$_search_engine_exclude_from_index_per_class[$this->owner->ClassName];
            if($exclude === false) {
                if ($this->owner->hasMethod('SearchEngineExcludeFromIndexProvider')) {
                    $exclude = $this->owner->SearchEngineExcludeFromIndexProvider();
                } else {
                    $exclude = Config::inst()->get($this->owner->ClassName, 'search_engine_exclude_from_index');
                }
                //special case SiteTree
                if ($this->owner->hasExtension(Versioned::class)) {
                    if ($this->owner->ShowInSearch && $this->owner->IsPublished()) {
                        //do nothing - no need to exclude
                    } else {
                        $exclude = true;
                    }
                }
            }
            //if it is to be excluded then remove from
            //search index.
            if ($exclude) {
                $exclude = true;
                // CAN NOT RUN THIS HERE!!!!
                // $this->SearchEngineDeleteFromIndexing();
            } else {
                $exclude = false;
            }

            self::$_search_engine_exclude_from_index[$key] = $exclude;
        }

        return self::$_search_engine_exclude_from_index[$key];
    }



}
