<?php

namespace Sunnysideup\SearchSimpleSmart\Extensions;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Permission;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;
use Sunnysideup\SearchSimpleSmart\Api\ExportKeywordList;
use Sunnysideup\SearchSimpleSmart\Api\SearchEngineDataObjectApi;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineFullContent;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;

/**
 * Add this DataExtension to any object that you would like to make
 * searchable.
 */


class SearchEngineMakeSearchable extends DataExtension
{
    /**
     * @var int
     */
    private $_onAfterWriteCount = [];

    /**
     * @var array
     */
    private static $_search_engine_exclude_from_index = [];

    /**
     * @var array
     */
    private static $_search_engine_exclude_from_index_per_class = [];

    ############################
    # do stuff ....
    ############################

    /**
     * deletes cached search results
     * sets stage to LIVE
     * indexes the current object.
     * @param SearchEngineDataObject $searchEngineDataObject
     * @param bool $withModeChange everything necessary for indexings.
     *                        Setting this to false means the stage will not be set
     *                        and the cache will not be cleared.
     */
    public function doSearchEngineIndex($searchEngineDataObject = null, $withModeChange = true)
    {
        //last check...
        if (! $searchEngineDataObject) {
            $searchEngineDataObject = SearchEngineDataObjectApi::find_or_make($this->owner);
        } else {
            if ($this->SearchEngineExcludeFromIndex()) {
                $searchEngineDataObject = null;
            }
            //do nothing
        }
        if ($searchEngineDataObject) {
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
    public function SearchEngineFullContentForIndexingBuild($item = null)
    {
        if (! $item) {
            $item = SearchEngineDataObjectApi::find_or_make($this->owner);
        }
        if ($item) {
            return $item->SearchEngineFullContentForIndexingBuild($this->owner);
        }

        return [];
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
        $item = SearchEngineDataObjectApi::find_or_make($this->owner);
        if ($item) {
            $field = 'SearchEngineKeywords_Level' . $level;

            return $item->{$field}();
        }

        return SearchEngineKeyword::get()->filter(['ID' => 0]);
    }

    ############################
    # display....
    ############################

    /**
     * returns a template for formatting the object
     * in the search results.
     *
     * @param bool $moreDetails
     *
     * @return array|null
     */
    public function SearchEngineResultsTemplates($moreDetails = false)
    {
        $item = SearchEngineDataObjectApi::find_or_make($this->owner);
        if ($item) {
            $item->SearchEngineResultsTemplates($this->owner, $moreDetails);
        }
        return [];
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
                if (! $this->owner->hasMethod('getSettingsFields')) {
                    $this->updateSettingsFields($fields);
                } elseif ($this->owner instanceof SiteTree) {
                    $fields->addFieldToTab(
                        'Root.Main',
                        LiteralField::create(
                            'SearchEngineHeader',
                            '<h2>
                                See
                                <a href="/admin/pages/settings/show/' . $this->owner->ID . '/">Settings Tab</a>
                                for Search Engine Details
                            </h2>'
                        )
                    );
                }
            }
        }
    }

    public function updateSettingsFields(FieldList $fields)
    {
        if (SiteConfig::current_site_config()->SearchEngineDebug || Permission::check('SEARCH_ENGINE_ADMIN')) {
            if ($this->SearchEngineExcludeFromIndex()) {
                return;
            }
            $item = SearchEngineDataObjectApi::find_or_make($this->owner);
            if ($item) {
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
                        SearchEngineDataObject::get()->filter(['DataObjectClassName' => $this->owner->ClassName, 'DataObjectID' => $this->owner->ID]),
                        $config
                    )
                );
            }
        }
    }

    public function SearchEngineFieldsToBeIndexedHumanReadable($includeExample = false)
    {
        $item = SearchEngineDataObjectApi::find_or_make($this->owner);
        if ($item) {
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
     * also delete SearchEngineDataObject
     * and all that relates to it.
     */
    public function onBeforeDelete()
    {
        $this->SearchEngineDeleteFromIndexing();
        //todo: make sure that the linked objects are also reindexed.
    }

    /**
     * delete SearchEngineDataObject
     * and all that relates to it.
     */
    public function onAfterUnpublish()
    {
        $this->SearchEngineDeleteFromIndexing();
        //todo: make sure that the linked objects are also reindexed.
    }

    /**
     * Mark for update
     */
    public function onBeforeWrite()
    {
        $alsoTrigger = $this->owner->SearchEngineAlsoTrigger();
        if (is_array($alsoTrigger) && count($alsoTrigger)) {
            foreach ($alsoTrigger as $details) {
                $className = $details['ClassName'];
                $id = $details['ID'];
                if ($this->owner->ID === $id && $this->owner->ClassName = $className) {
                    user_error('Object can not trigger itself');
                    die();
                }
                $obj = $className::get()->byID($id);
                if ($obj->hasExtension(Versioned::class)) {
                    $doPublish = $obj->isPublished();
                    $obj->writeToStage(Versioned::DRAFT);
                    if ($doPublish) {
                        $obj->publish(Versioned::DRAFT, Versioned::LIVE);
                    }
                } else {
                    $obj->write();
                }
            }
        }
    }

    /**
     * Mark for update
     */
    public function onAfterWrite()
    {
        if ($this->owner->SearchEngineExcludeFromIndex()) {
            //do nothing...
        } else {
            if (! isset($this->_onAfterWriteCount[$this->owner->ID])) {
                $this->_onAfterWriteCount[$this->owner->ID] = 0;
            }
            register_shutdown_function([$this->owner, 'indexMeOnShutDown']);
        }
    }

    public function indexMeOnShutDown()
    {
        $item = SearchEngineDataObjectApi::find_or_make($this->owner);
        if ($item) {
            $item->write();
            // ExportKeywordList::export_keyword_list();
            SearchEngineDataObjectToBeIndexed::add($item);
        }
    }

    public function SearchEngineDeleteFromIndexing()
    {
        $item = SearchEngineDataObjectApi::find_or_make($this->owner, $doNotMake = true);
        if ($item && $item->exists()) {
            $item->delete();
        }
    }

    #####################################
    # get index data ...
    #####################################

    /**
     * returns array like this:
     * 1 => array('Title', 'MenuTitle')
     * 2 => array('Content')
     *
     * can be replaced by a method in the object itself!
     *
     * @return array
     */
    public function SearchEngineFieldsForIndexing()
    {
        $item = SearchEngineDataObjectApi::find_or_make($this->owner);
        if ($item) {
            return $item->SearchEngineFieldsForIndexing($this->owner);
        }
        return [
            1 => [],
            2 => [],
        ];
    }

    /**
     * Indexed Full Content Data
     * @return DataList
     */
    public function SearchEngineDataObjectFullContent()
    {
        $item = SearchEngineDataObjectApi::find_or_make($this->owner);
        if ($item) {
            return $item->SearchEngineFullContents();
        }

        return SearchEngineFullContent::get()->filter(['ID' => 0]);
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
        $item = SearchEngineDataObjectApi::find_or_make($this->owner, false);
        if ($item && $item->exists()) {
            return $item->SearchEngineDataObjectToBeIndexed()->filter(['Completed' => true])->count();
        }

        return false;
    }

    /**
     * @return boolean
     */
    public function SearchEngineExcludeFromIndex()
    {
        if (empty($this->owner->ID) || empty($this->owner->ClassName)) {
            return true;
        }

        $key = $this->owner->ClassName . '_' . $this->owner->ID;
        if (! isset(self::$_search_engine_exclude_from_index[$key])) {
            if (! isset(self::$_search_engine_exclude_from_index_per_class[$this->owner->ClassName])) {
                $classNameList = SearchEngineDataObjectApi::searchable_class_names();
                $exclude = isset($classNameList[$this->owner->ClassName]) ? false : true;
                self::$_search_engine_exclude_from_index_per_class[$this->owner->ClassName] = $exclude;
            }
            $exclude = self::$_search_engine_exclude_from_index_per_class[$this->owner->ClassName];
            if ($exclude === false) {
                if ($this->owner->hasMethod('SearchEngineExcludeFromIndexProvider')) {
                    $exclude = $this->owner->SearchEngineExcludeFromIndexProvider();
                } else {
                    $exclude = Config::inst()->get($this->owner->ClassName, 'search_engine_exclude_from_index');
                }

                //special case SiteTree
                if (isset($this->owner->ShowInSearch)) {
                    if (! $this->owner->ShowInSearch) {
                        $exclude = true;
                    }
                }

                //important! has it been published?
                if ($this->owner->hasExtension(Versioned::class)) {
                    if ($this->owner->IsPublished()) {
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
