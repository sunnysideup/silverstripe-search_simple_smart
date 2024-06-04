<?php

namespace Sunnysideup\SearchSimpleSmart\Extensions;

use DNADesign\Elemental\Models\BaseElement;
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
use Exception;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\TextareaField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;

/**
 * Add this DataExtension to any object that you would like to make
 * searchable.
 */
class SearchEngineMakeSearchable extends DataExtension
{
    /**
     * @var array
     */
    protected static $_search_engine_exclude_from_index = [];

    /**
     * @var array
     */
    protected static $_search_engine_exclude_from_index_per_class = [];

    /**
     * @var array
     */
    private $_onAfterWriteCount = [];

    private static $db = [
        'KeywordStuffer' => 'Text'
    ];


    //###########################
    // do stuff ....
    //###########################

    /**
     * deletes cached search results
     * sets stage to LIVE
     * indexes the current object.
     *  Setting $withModeChange to false means the stage will not be set
     *  and the cache will not be cleared.
     *
     */
    public function doSearchEngineIndex(?SearchEngineDataObject $searchEngineDataObject = null, ?bool $withModeChange = true)
    {
        $owner = $this->getOwner();
        //last check...
        if (! $searchEngineDataObject) {
            $searchEngineDataObject = SearchEngineDataObjectApi::find_or_make($owner);
        } else {
            if ($this->SearchEngineExcludeFromIndex()) {
                //do nothing
                $searchEngineDataObject = null;
            }
        }

        if ($searchEngineDataObject) {
            $searchEngineDataObject->doSearchEngineIndex($owner, $withModeChange);
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
     * @param null|mixed $item
     *
     * @return array
     */
    public function SearchEngineFullContentForIndexingBuild($item = null)
    {
        $owner = $this->getOwner();
        if (! $item) {
            $item = SearchEngineDataObjectApi::find_or_make($owner);
        }

        if ($item) {
            return $item->SearchEngineFullContentForIndexingBuild($owner);
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
     *      ).
     *
     * @return array
     */
    public function SearchEngineAlsoTrigger()
    {
        $owner = $this->getOwner();
        if ($owner->hasMethod('SearchEngineAlsoTriggerProvider')) {
            return $owner->SearchEngineAlsoTriggerProvider();
        }

        return [];
    }

    //###########################
    // searches
    //###########################

    /**
     * Indexed Keywords.
     *
     * @param mixed $level
     *
     * @return DataList
     */
    public function SearchEngineKeywordDataObjectMatches($level = 1)
    {
        $owner = $this->getOwner();
        $item = SearchEngineDataObjectApi::find_or_make($owner);
        if ($item) {
            $field = 'SearchEngineKeywords_Level' . $level;

            return $item->{$field}();
        }

        return SearchEngineKeyword::get()->filter(['ID' => 0]);
    }

    //###########################
    // display....
    //###########################

    /**
     * returns a template for formatting the object
     * in the search results.
     *
     * @param bool $moreDetails
     *
     * @return null|array
     */
    public function SearchEngineResultsTemplates($moreDetails = false)
    {
        $owner = $this->getOwner();
        $item = SearchEngineDataObjectApi::find_or_make($owner);
        if ($item) {
            return $item->SearchEngineResultsTemplates($owner, $moreDetails);
        }

        return [];
    }

    //###########################
    // CMS
    //###########################

    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->getOwner();
        if ($owner->SearchEngineExcludeFromIndex()) {
            return;
        }

        if (SiteConfig::current_site_config()->SearchEngineDebug || Permission::check('SEARCH_ENGINE_ADMIN')) {
            if ($fields->fieldByName('Root')) {
                $fields->findOrMakeTab('Root.Main');
                $fields->removeFieldFromTab('Root.Main', 'SearchEngineDataObjectID');
                if (! $owner->hasMethod('getSettingsFields')) {
                    $this->updateSettingsFields($fields);
                } elseif ($owner instanceof SiteTree) {
                    $fields->addFieldToTab(
                        'Root.Keywords',
                        LiteralField::create(
                            'SearchEngineHeader',
                            '<h2>
                                See
                                <a href="/admin/pages/settings/show/' . $owner->ID . '#Root_SearchEngine">Settings Tab</a>
                                for Keyword Search Details
                            </h2>'
                        )
                    );
                }
            }
        }
    }

    public function updateSettingsFields(FieldList $fields)
    {
        $owner = $this->getOwner();
        if (SiteConfig::current_site_config()->SearchEngineDebug || Permission::check('SEARCH_ENGINE_ADMIN')) {
            if ($this->SearchEngineExcludeFromIndex()) {
                return;
            }

            $item = SearchEngineDataObjectApi::find_or_make($owner);
            if ($item) {
                $hasBeenIndexed = $this->SearchEngineIsIndexed() ? 'yes' : 'no';
                $toBeIndexed = $item->toBeIndexed() ? 'yes' : 'no';
                $re = $item->toBeReIndexed() ? 're' : '';
                $fields->addFieldsToTab(
                    'Root.SearchEngine',
                    [
                        TextareaField::create('KeywordStuffer', 'Keywords to add to search engine')
                            ->setDescription('Adding keywords here will ensure this records ranks well for those keywords.'),
                        ReadonlyField::create('HasBeenIndexed', 'Has been indexed', $hasBeenIndexed),
                        ReadonlyField::create('LastIndexed', 'Last Index', $this->SearchEngineIsIndexed() ? $owner->LastEdited : 'n/a'),
                        ReadonlyField::create('ToBeIndexed', 'On the list to be '.$re.'indexed', $toBeIndexed),
                        ReadonlyField::create(
                            'LinkToSearchEngineDataObject',
                            'More details',
                            DBHTMLText::create_field('HTMLText', '<a href="'.$item->CMSEditLink().'">Open Index Entry</a>')
                        )
                    ]
                );
            }
        }
    }

    public function SearchEngineFieldsToBeIndexedHumanReadable(?bool $includeExample = false)
    {
        $owner = $this->getOwner();
        $item = SearchEngineDataObjectApi::find_or_make($owner);
        if ($item) {
            return $item->SearchEngineFieldsToBeIndexedHumanReadable($owner, $includeExample);
        }
    }

    //###########################
    // on Before And After CRUD ...
    //###########################

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
     * Mark for update.
     */
    public function onBeforeWrite()
    {
        $owner = $this->getOwner();
        $alsoTrigger = $owner->SearchEngineAlsoTrigger();
        if (is_array($alsoTrigger) && count($alsoTrigger)) {
            foreach ($alsoTrigger as $details) {
                $className = $details['ClassName'];
                $id = $details['ID'];
                if ($owner->ID === $id && $owner->ClassName = $className) {
                    user_error('Object can not trigger itself');
                    die();
                }

                $obj = $className::get()->byID($id);
                if ($obj->hasExtension(Versioned::class)) {
                    $doPublish = $obj->isPublished() && ! $obj->isModifiedOnDraft();
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
     * Mark for update.
     */
    public function onAfterWrite()
    {
        $owner = $this->getOwner();
        if ($owner->SearchEngineExcludeFromIndex()) {
            //do nothing...
        } else {
            if (! isset($this->_onAfterWriteCount[$owner->ID])) {
                $this->_onAfterWriteCount[$owner->ID] = 0;
            }

            register_shutdown_function([$owner, 'indexMeOnShutDown']);
        }
    }

    public function indexMeOnShutDown()
    {
        $owner = $this->getOwner();
        $item = SearchEngineDataObjectApi::find_or_make($owner);
        if ($item) {
            $item->write();
            // ExportKeywordList::export_keyword_list();
            SearchEngineDataObjectToBeIndexed::add($item);
        }
    }

    public function SearchEngineDeleteFromIndexing()
    {
        $owner = $this->getOwner();
        $item = SearchEngineDataObjectApi::find_or_make($owner, $doNotMake = true);
        if ($item && $item->exists()) {
            $item->delete();
        }
    }


    //####################################
    // get index data ...
    //####################################

    /**
     * returns array like this:
     * 1 => array('Title', 'MenuTitle')
     * 2 => array('Content').
     *
     * can be replaced by a method in the object itself!
     *
     * @return array
     */
    public function SearchEngineFieldsForIndexing()
    {
        $owner = $this->getOwner();
        $item = SearchEngineDataObjectApi::find_or_make($owner);
        if ($item) {
            return $item->SearchEngineFieldsForIndexing($owner);
        }

        return [
            1 => [],
            2 => [],
        ];
    }

    /**
     * Indexed Full Content Data.
     *
     * @return DataList
     */
    public function SearchEngineDataObjectFullContent()
    {
        $owner = $this->getOwner();
        $item = SearchEngineDataObjectApi::find_or_make($owner);
        if ($item) {
            return $item->SearchEngineFullContents();
        }

        return SearchEngineFullContent::get()->filter(['ID' => 0]);
    }

    //#####################################
    // Status
    //#####################################

    /**
     * Is this object indexed?
     *
     * @return bool
     */
    public function SearchEngineIsIndexed(): bool
    {
        $owner = $this->getOwner();
        $item = SearchEngineDataObjectApi::find_or_make($owner, false);
        if ($item && $item->exists()) {
            return $item->SearchEngineIsIndexed();
        }

        return false;
    }

    /**
     * @return bool
     */
    public function SearchEngineExcludeFromIndex()
    {
        $owner = $this->getOwner();

        if (empty($owner->ID) || empty($owner->ClassName)) {
            return true;
        }
        if(class_exists(BaseElement::class) && isset($owner->ShowTitle) && $owner->ShowTitle === false) {
            return true;
        }
        if(!$owner->Title) {
            $owner->Title = $owner->getTitle();
            if($owner->Title && $owner->Title === '#' . $owner->ID) {
                $owner->Title = '';
            }
        }
        try {
            if(!$owner->exists()) {
                return true;
            }
        } catch (Exception $e) {
            return true;
            // do nothing
        }
        if(trim((string) $owner->Link()) === '' ||  trim((string)$owner->Title) === '') {
            return true;
        }

        $key = $owner->ClassName . '_' . $owner->ID;
        if (! isset(self::$_search_engine_exclude_from_index[$key])) {
            if (! isset(self::$_search_engine_exclude_from_index_per_class[$owner->ClassName])) {
                $classNameList = SearchEngineDataObjectApi::searchable_class_names();
                $exclude = ! isset($classNameList[$owner->ClassName]);
                self::$_search_engine_exclude_from_index_per_class[$owner->ClassName] = $exclude;
            }

            $exclude = self::$_search_engine_exclude_from_index_per_class[$owner->ClassName];
            if (false === $exclude) {
                if ($owner->hasMethod('SearchEngineExcludeFromIndexProvider')) {
                    $exclude = $owner->SearchEngineExcludeFromIndexProvider();
                } else {
                    $exclude = Config::inst()->get($owner->ClassName, 'search_engine_exclude_from_index');
                }

                //special case SiteTree
                if (isset($owner->ShowInSearch)) {
                    if (! $owner->ShowInSearch) {
                        $exclude = true;
                    }
                }

                //important! has it been published?
                if ($owner->hasExtension(Versioned::class)) {
                    if ($owner->IsPublished()) {
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

    /**
     * @param bool $classNameOnly
     */
    public function getSearchEngineKey($classNameOnly = false): string
    {
        $owner = $this->getOwner();
        if ($classNameOnly) {
            return $owner->ClassName . '';
        }

        return $owner->ID . '_' . $owner->ClassName;
    }
}
