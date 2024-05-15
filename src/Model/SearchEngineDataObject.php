<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use SilverStripe\Assets\Folder;
use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;
use Sunnysideup\SearchSimpleSmart\Api\SearchEngineDataObjectApi;
use Sunnysideup\SearchSimpleSmart\Api\SearchEngineSourceObjectApi;

/**
 * List of dataobjects that are indexed.
 */
class SearchEngineDataObject extends DataObject
{
    /**
     * used for caching...
     *
     * @var array
     */
    protected static $_object_class_name = [];

    protected static $_source_objects = [];

    protected static $_source_objects_exists = [];

    protected static $_special_sort_group = [];

    //############################################
    // CONFIG
    //############################################

    /**
     * List of Fields that are level one (most important)
     * e.g. Title, Name, etc...
     *
     * @var array
     */
    private static $search_engine_default_level_one_fields = [
        'Title',
        'Name',
        'Summary',
        'Heading',
        'Intro',
        'URLSegment',
        'MenuTitle',
        'FirstName',
        'Surname',
    ];

    /**
     * List of fields that should not be included by default.
     *
     * @var array
     */
    private static $search_engine_default_excluded_db_fields = [
        'Autocomplete',
        'CanEditType',
        'CanEditType',
        'CanViewType',
        'ClassName',
        'CopyContentFrom',
        'Created',
        'EditorGroups',
        'EmptyString',
        'ExtraClass',
        'ExtraMeta',
        'FileTracking',
        'ID',
        'LastEdited',
        'LinkTracking',
        'Password',
        'QueryString',
        'RedirectionType',
        'SpamFieldSettings',
        'Style',
        'UploadedFile',
        'ViewerGroups',
    ];

    /**
     * Order of fields that can be used to establish a SORT date for the
     * source object.
     *
     * @var array
     */
    private static $search_engine_date_fields_for_sorting = [
        'PublishDate',
        'Created',
        'LastEdited',
    ];

    /**
     * @var array
     */
    private static $classes_to_include = [];

    /**
     * @var array
     */
    private static $classes_to_exclude = [
        ErrorPage::class,
        VirtualPage::class,
        RedirectorPage::class,
        Folder::class,
    ];

    /**
     * Defines the database table name.
     *
     * @var string
     */
    private static $table_name = 'SearchEngineDataObject';

    /**
     * @var string
     */
    private static $singular_name = 'Searchable Item';

    /**
     * @var string
     */
    private static $plural_name = 'Searchable Items';

    /**
     * @var array
     */
    private static $db = [
        'DataObjectClassName' => 'Varchar(150)',
        'DataObjectID' => 'Int',
        'Recalculate' => 'Boolean',
        'DataObjectDate' => 'Datetime',
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'SearchEngineDataObjectToBeIndexed' => SearchEngineDataObjectToBeIndexed::class,
        'SearchEngineFullContents' => SearchEngineFullContent::class,
    ];

    //should work but does not
    //private static $belongs_many_many = array(
    //	'SearchEngineKeywords_Level1' => 'SearchEngineKeyword.SearchEngineDataObjects_Level1',
    //	'SearchEngineKeywords_Level2' => 'SearchEngineKeyword.SearchEngineDataObjects_Level2',
    //	'SearchEngineKeywords_Level3' => 'SearchEngineKeyword.SearchEngineDataObjects_Level3'
    //);

    /**
     * @var array
     */
    private static $belongs_many_many = [
        'SearchEngineKeywords_Level1' => SearchEngineKeyword::class,
    ];

    /**
     * @var array
     */
    private static $many_many = [
        'SearchEngineKeywords_Level2' => SearchEngineKeyword::class,
    ];

    /**
     * @var array
     */
    private static $many_many_extraFields = [
        'SearchEngineKeywords_Level2' => ['Count' => 'Int'],
        //'SearchEngineDataObjects_Level3' => array('Count' => 'Int')
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'DataObjectClassName' => true,
        'DataObjectID' => true,
        'DataObjectDate' => true,
    ];

    /**
     * @var array
     */
    private static $default_sort = [
        'DataObjectClassName' => 'ASC',
        'DataObjectID' => 'ASC',
    ];

    /**
     * @var array
     */
    private static $required_fields = [
        'DataObjectClassName' => true,
        'DataObjectID' => true,
    ];

    /**
     * @var array
     */
    private static $casting = [
        'Title' => 'Varchar',
        'HTMLOutput' => 'HTMLText',
        'HTMLOutputMoreDetails' => 'HTMLText',
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Title' => 'Title',
        'LastEdited.Nice' => 'Last Updated',
        'DataObjectDate.Nice' => 'Sort Date',
        'SearchEngineDataObjectToBeIndexed.Count' => 'Indexed Times',
        'SearchEngineKeywords_Level1.Count' => 'Level1 Keywords',
        'SearchEngineKeywords_Level2.Count' => 'Level2 Keywords',
    ];

    /**
     * @var array
     */
    private static $searchable_fields = [
        'SearchEngineKeywords_Level1.Keyword' => 'PartialMatchFilter',
        'SearchEngineKeywords_Level2.Keyword' => 'PartialMatchFilter',
    ];

    /**
     * @var array
     */
    private static $field_labels = [
        'DataObjectClassName' => 'Object',
        'DataObjectID' => 'ID',
        'DataObjectDate' => 'Sort Date',
        'SearchEngineDataObjectToBeIndexed' => 'Listed for indexing',
    ];

    private $recalculateCount = 0;

    //####################
    // do stuff
    //####################

    private $timeMeasure = [];

    //############################################
    // CRUD
    //############################################

    /**
     * @param Member $member
     * @param mixed  $context
     *
     * @return bool
     */
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    public function canEdit($member = null)
    {
        return parent::canEdit($member) && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    public function canDelete($member = null)
    {
        return parent::canDelete($member) && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    public function canView($member = null)
    {
        return parent::canView() && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    public function SearchEngineSourceObjectSortDate($sourceObject = null)
    {
        if (!$sourceObject) {
            $sourceObject = $this->SourceObject();
        }

        return Injector::inst()->get(SearchEngineSourceObjectApi::class)
            ->SearchEngineSourceObjectSortDate($sourceObject)
        ;
    }

    /**
     * returns array like this:
     * 1 => array('Title', 'MenuTitle')
     * 2 => array('Content').
     *
     * @param null|mixed $sourceObject
     *
     * @return array
     */
    public function SearchEngineFieldsForIndexing($sourceObject = null)
    {
        if (!$sourceObject) {
            $sourceObject = $this->SourceObject();
        }

        return Injector::inst()->get(SearchEngineSourceObjectApi::class)
            ->FieldsForIndexing($sourceObject)
        ;
    }

    public function getObjectClassName(): string
    {
        $className = $this->DataObjectClassName;
        if (!class_exists($className)) {
            return 'ERROR - class not found';
        }

        if (!isset(self::$_object_class_name[$className])) {
            $objectClassName = Injector::inst()->get($className)->singular_name();
            self::$_object_class_name[$className] = $objectClassName;
        }

        return self::$_object_class_name[$className];
    }

    /**
     * @casted variable
     */
    public function getTitle(): string
    {
        $objectClassName = $this->getObjectClassName();
        $object = $this->SourceObject();
        $objectName = $object ? $object->getTitle() : 'ERROR: NOT FOUND';

        return $objectClassName . ': ' . $objectName;
    }

    public function SourceObject(): ?DataObject
    {
        $key = $this->getSearchEngineKey();
        if (!isset(self::$_source_objects[$key])) {
            $className = $this->DataObjectClassName;
            if ($className && class_exists($className)) {
                $id = $this->DataObjectID;
                if ($id) {
                    self::$_source_objects[$key] = $className::get()->byID($id);
                }
            }
        }

        return self::$_source_objects[$key] ?? null;
    }

    public function SourceObjectExists(): bool
    {
        $key = $this->getSearchEngineKey();
        if (!isset(self::$_source_objects_exists[$key])) {
            self::$_source_objects_exists[$key] = false;
            $className = $this->DataObjectClassName;
            $id = $this->DataObjectID;
            if ($id && $className && class_exists($className)) {
                self::$_source_objects_exists[$key] = $className::get()
                    ->filter(['ID' => $id])
                    ->exists()
                ;
            }
        }

        return self::$_source_objects_exists[$key];
    }

    /**
     * @return string
     */
    public function RecordClickLink()
    {
        return 'searchenginerecordclick/add/' . $this->ID . '/';
    }

    /**
     * if there are special sorts groups this method helps to
     * show them in the templates.
     *
     * In the templates you do:
     *     <h2>Results</h2>
     *     <% loop $Results.GroupedBy(SpecialSortGroup) %>
     *         <ul>
     *         <% loop $Children %>
     *             <li>$Title ($Created.Nice)</li>
     *         <% end_loop %>
     *         </ul>
     *     <% end_loop %>
     *
     * @return string
     */
    public function SpecialSortGroup()
    {
        $className = $this->getSearchEngineKey(true);
        if (!isset(self::$_special_sort_group[$className])) {
            self::$_special_sort_group[$className] = '';
            $classGroups = Config::inst()->get(SearchEngineSortByDescriptor::class, 'class_groups');
            if (is_array($classGroups) && count($classGroups)) {
                self::$_special_sort_group[$className] = 'SortGroup999';
                foreach ($classGroups as $level => $classes) {
                    if (in_array($this->DataObjectClassName, $classes, true)) {
                        self::$_special_sort_group[$className] = 'SortGroup' . $level;

                        break;
                    }
                }
            }
        }

        return self::$_special_sort_group[$className];
    }

    public function SearchEngineExcludeFromIndex(): bool
    {
        $sourceObject = $this->SourceObject();
        if($sourceObject) {
            return $sourceObject->SearchEngineExcludeFromIndex();
        } else {
            return true;
        }
    }

    //############################################
    // CMS
    //############################################

    /**
     * CMS Fields.
     *
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->replaceField(
            'DataObjectClassName',
            ReadonlyField::create(
                'DataObjectClassName',
                'Class Name'
            )
        );
        $fields->replaceField(
            'DataObjectID',
            ReadonlyField::create(
                'DataObjectID',
                'Record ID'
            )
        );
        $fields->addFieldToTab(
            'Root.Main',
            ReadonlyField::create(
                'Title',
                'Title'
            ),
            'DataObjectClassName'
        );
        $object = $this->SourceObject();
        if ($object && $object->hasMethod('CMSEditLink')) {
            $fields->addFieldToTab(
                'Root.Main',
                ReadonlyField::create(
                    'CMSLink',
                    'Open in CMS',
                    DBField::create_field('HTMLText', '<a href="' . $object->CMSEditLink() . '" target="_blank">open to edit</a>')
                )
            );
        }

        if ($object && ($object->hasMethod('Link'))) {
            $fields->addFieldToTab(
                'Root.Main',
                ReadonlyField::create(
                    'FrontEndLink',
                    'Open on front-end',
                    DBField::create_field('HTMLText', '<a href="' . $object->Link() . '" target="_blank">open to view</a>')
                )
            );
        }

        if ($myTab = $fields->fieldByName('Root.SearchEngineKeywords_Level2')) {
            $fields->removeFieldFromTab('Root', 'SearchEngineKeywords_Level2');
            $fields->fieldByName('Root')->push($myTab);
        }

        return $fields;
    }

    public function CMSEditLink()
    {
        $className = str_replace('\\', '-', (string) self::class);

        return '/admin/searchengine/' . $className . '/EditForm/field/' . $className . '/item/' . $this->ID . '/edit';
    }

    //####################
    // display
    //####################

    /**
     * @param bool $moreDetails
     *
     * @return string
     */
    public function getHTMLOutput($moreDetails = false)
    {
        $sourceObject = $this->SourceObject();

        return Injector::inst()->get(SearchEngineSourceObjectApi::class)
            ->getHTMLOutput(
                $sourceObject,
                $moreDetails
            )
        ;
    }

    /**
     * @return string
     */
    public function getHTMLOutputMoreDetails()
    {
        return $this->getHTMLOutput(true);
    }

    /**
     * returns a template for formatting the object
     * in the search results.
     *
     * @param bool       $moreDetails
     * @param null|mixed $sourceObject
     *
     * @return array
     */
    public function SearchEngineResultsTemplates($sourceObject = null, $moreDetails = false)
    {
        if (!$sourceObject) {
            $sourceObject = $this->SourceObject();
        }

        return Injector::inst()->get(SearchEngineSourceObjectApi::class)
            ->SearchEngineResultsTemplates(
                $sourceObject,
                $moreDetails
            )
        ;
    }

    public function SearchEngineFieldsToBeIndexedHumanReadable($sourceObject = null, $includeExample = false)
    {
        if (!$sourceObject) {
            $sourceObject = $this->SourceObject();
        }

        return Injector::inst()->get(SearchEngineSourceObjectApi::class)
            ->SearchEngineFieldsToBeIndexedHumanReadable(
                $sourceObject,
                $includeExample
            )
        ;
    }

    /**
     * deletes cached search results
     * sets stage to LIVE
     * indexes the current object.
     *
     * @param SearchEngineDataObject $sourceObject
     * @param DataObject             $sourceObject
     * @param bool                   $withModeChange - Setting this to false means the stage
     * @param bool                   $timeMeasure    - do time measure?
     */
    public function doSearchEngineIndex($sourceObject = null, $withModeChange = true, $timeMeasure = false)
    {
        if ($timeMeasure) {
            $this->timeMeasure = [];
        }

        if (!$sourceObject) {
            $sourceObject = $this->SourceObject();
        }

        $startTime = 0;
        if ($sourceObject) {
            if ($withModeChange) {
                SearchEngineDataObjectApi::start_indexing_mode();
            }

            //add date!
            $this->DataObjectDate = $this->SearchEngineSourceObjectSortDate($sourceObject);
            $this->write();

            if ($timeMeasure) {
                $startTime = microtime(true);
            }

            //get the full content
            $fullContentArray = $this->SearchEngineFullContentForIndexingBuild($sourceObject);

            if ($timeMeasure) {
                $this->timeMeasure['FullContentBuild'] = microtime(true) - $startTime;
                $startTime = microtime(true);
            }

            //add the full content
            SearchEngineFullContent::add_data_object_array($this, $fullContentArray);

            if ($timeMeasure) {
                $this->timeMeasure['AddContent'] = microtime(true) - $startTime;
            }

            if ($withModeChange) {
                SearchEngineDataObjectApi::end_indexing_mode();
            }
        } else {
            echo 'Can not find object';
        }
    }

    public function getTimeMeasure()
    {
        return $this->timeMeasure;
    }

    /**
     * returns a full-text version of an object like this:
     * array(
     *   1 => 'bla',
     *   2 => 'foo',
     * );
     * where 1 and 2 are the levels of importance of each string.
     *
     * @param null|mixed $sourceObject
     *
     * @return array
     */
    public function SearchEngineFullContentForIndexingBuild($sourceObject = null)
    {
        if (!$sourceObject) {
            $sourceObject = $this->SourceObject();
        }

        return Injector::inst()->get(SearchEngineSourceObjectApi::class)
            ->ContentForIndexBuilding($sourceObject)
        ;
    }

    //############################################
    // DEFINITIONS
    //############################################

    /**
     * @param bool $classNameOnly
     */
    public function getSearchEngineKey($classNameOnly = false): string
    {
        if ($classNameOnly) {
            return $this->DataObjectClassName . '';
        }

        return $this->DataObjectID . '_' . $this->DataObjectClassName;
    }

    /**
     * make sure all the references are deleted as well.
     */
    protected function onBeforeDelete()
    {
        ///DataObject to be Indexed
        $this->flushCache();
        $objects = SearchEngineDataObjectToBeIndexed::get()
            ->filter(['SearchEngineDataObjectID' => $this->ID])
        ;
        foreach ($objects as $object) {
            $object->delete();
        }

        //keywords
        $this->SearchEngineKeywords_Level1()->removeAll();
        $this->SearchEngineKeywords_Level2()->removeAll();
        //full content
        $objects = $this->SearchEngineFullContents();
        foreach ($objects as $object) {
            $object->delete();
        }

        parent::onBeforeDelete();
        $this->flushCache();
    }

    /**
     * Event handler called before writing to the database.
     */
    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->Recalculate) {
            //in databas object, make sure onAfterWrite runs!
            $this->forceChange();
        }
    }

    /**
     * Event handler called after writing to the database.
     */
    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->Recalculate && $this->recalculateCount < 2) {
            ++$this->recalculateCount;
            if($this->SearchEngineExcludeFromIndex() === true) {
                $this->delete();
            } else {
                $this->doSearchEngineIndex();
                $this->write();
            }
        } elseif ($this->Recalculate) {
            $this->Recalculate = false;
            $this->write();
        }
    }
}
