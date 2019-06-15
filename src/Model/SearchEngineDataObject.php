<?php

namespace Sunnysideup\SearchSimpleSmart\Model;
use SilverStripe\Security\Permission;

use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineFullContent;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\Assets\Folder;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBString;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Extensions\SearchEngineMakeSearchable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;
use Psr\SimpleCache\CacheInterface;
/**
 * List of dataobjects that are indexed.
 */

class SearchEngineDataObject extends DataObject
{








    #############################################
    # QUICK STUFF
    #############################################

    /**
     *
     * @param DataObject $obj
     * @param bool $doNotMake
     * @return SearchEngineDataObject|null
     */
    public static function find_or_make($obj, $doNotMake = false)
    {

        if ($obj->hasExtension(SearchEngineMakeSearchable::class)) {
            if($obj->SearchEngineExcludeFromIndex()) {

                return null;
            } else {
                $fieldArray = array(
                    'DataObjectClassName' => $obj->ClassName,
                    'DataObjectID' => $obj->ID
                );
                $item = DataObject::get_one(SearchEngineDataObject::class, $fieldArray);
                if ($item || $doNotMake) {
                    //do nothing;
                } else {
                    $item = SearchEngineDataObject::create($fieldArray);
                    $item->write();
                }

                return $item;
            }
        } else {
            user_error('DataObject expected, instead, the following was provided: '.var_dump($obj));
        }
    }









    #############################################
    # CONFIG
    #############################################

    /**
     * List of Fields that are level one (most important)
     * e.g. Title, Name, etc...
     * @var Array
     */
    private static $search_engine_default_level_one_fields = [];

    /**
     * List of fields that should not be included by default
     * @var Array
     */
    private static $search_engine_default_excluded_db_fields = array(
        'ReportClass',
        'CanViewType',
        'ExtraMeta',
        'CanEditType',
        'Password'
    );

    /**
     * Order of fields that can be used to establish a SORT date for the
     * source object.
     * @var Array
     */
    private static $search_engine_date_fields_for_sorting = array(
        'PublishDate',
        'Created',
        'LastEdited'
    );


    /**
     *
     * @var Array
     */
    private static $classes_to_include =[];

    /**
     *
     * @var Array
     */
    private static $classes_to_exclude = array(
        ErrorPage::class,
        VirtualPage::class,
        RedirectorPage::class,
        Folder::class
    );



    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SearchEngineDataObject';

    /**
     * @var string
     */
    private static $singular_name = 'Searchable Item';
    public function i18n_singular_name()
    {
        return $this->Config()->get('singular_name');
    }

    /**
     * @var string
     */
    private static $plural_name = 'Searchable Items';
    public function i18n_plural_name()
    {
        return $this->Config()->get('plural_name');
    }

    /**
     * @var array
     */
    private static $db = array(
        'DataObjectClassName' => 'Varchar(150)',
        'DataObjectID' => 'Int',
        'Recalculate' => 'Boolean',
        'DataObjectDate' => 'Datetime'
    );

    /**
     * @var array
     */
    private static $has_many = array(
        'SearchEngineDataObjectToBeIndexed' => SearchEngineDataObjectToBeIndexed::class,
        'SearchEngineFullContents' => SearchEngineFullContent::class
    );

    //should work but does not
    //private static $belongs_many_many = array(
    //	'SearchEngineKeywords_Level1' => 'SearchEngineKeyword.SearchEngineDataObjects_Level1',
    //	'SearchEngineKeywords_Level2' => 'SearchEngineKeyword.SearchEngineDataObjects_Level2',
    //	'SearchEngineKeywords_Level3' => 'SearchEngineKeyword.SearchEngineDataObjects_Level3'
    //);

    /**
     * @var array
     */
    private static $belongs_many_many = array(
        'SearchEngineKeywords_Level1' => SearchEngineKeyword::class,
    );

    /**
     * @var array
     */
    private static $many_many = array(
        'SearchEngineKeywords_Level2' => SearchEngineKeyword::class,
    );

    /**
     * @var array
     */
    private static $many_many_extraFields = array(
        'SearchEngineKeywords_Level2' => array('Count' => 'Int'),
        //'SearchEngineDataObjects_Level3' => array('Count' => 'Int')
    );

    /**
     * @var array
     */
    private static $indexes = array(
        'DataObjectClassName' => true,
        'DataObjectID' => true,
        'DataObjectDate' => true
    );

    /**
     * @var array
     */
    private static $default_sort = array(
        'DataObjectClassName' => 'ASC',
        'DataObjectID' => 'ASC'
    );

    /**
     * @var array
     */
    private static $required_fields = array(
        'DataObjectClassName' => true,
        'DataObjectID' => true
    );

    /**
     * @var array
     */
    private static $casting = array(
        'Title' => 'Varchar',
        'HTMLOutput' => 'HTMLText',
        'HTMLOutputMoreDetails' => 'HTMLText'
    );

    /**
     * @var array
     */
    private static $summary_fields = array(
        'Title' => 'Title',
        'LastEdited.Nice' => 'Last Updated',
        'DataObjectDate.Nice' => 'Sort Date',
        'SearchEngineDataObjectToBeIndexed.Count' => 'Indexed Times',
        'SearchEngineKeywords_Level1.Count' => 'Level1 Keywords',
        'SearchEngineKeywords_Level2.Count' => 'Level2 Keywords'
    );

    /**
     * @var array
     */
    private static $searchable_fields = array(
        'DataObjectClassName' => 'PartialMatchFilter'
    );

    /**
     * @var array
     */
    private static $field_labels = array(
        'DataObjectClassName' => 'Object',
        'DataObjectID' => 'ID',
        'DataObjectDate' => 'Sort Date',
        'SearchEngineDataObjectToBeIndexed' => 'Listed for indexing'
    );


    private static $_original_mode = null;

    public static function start_indexing_mode()
    {
        SearchEngineSearchRecord::flush();
        self::$_original_mode = Versioned::get_stage();
        Versioned::set_stage(Versioned::LIVE);
    }

    public static function end_indexing_mode()
    {
        Versioned::set_stage(self::$_original_mode);
    }





















    #############################################
    # CRUD
    #############################################

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canEdit($member = null, $context = [])
    {
        return parent::canEdit($member, $context) && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canDelete($member = null, $context = [])
    {
        return parent::canDelete($member, $context) && Permission::check('SEARCH_ENGINE_ADMIN');
    }

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canView($member = null, $context = [])
    {
        return parent::canView() && Permission::check('SEARCH_ENGINE_ADMIN');
    }




    /**
     * @param DataObject $obj
     */
    public static function remove($obj)
    {
        $item = self::find_or_make($obj, $doNotMake = true);
        if($item && $item->exists()) {
            $item->delete();
        }
    }

    /**
     * make sure all the references are deleted as well
     *
     */
    public function onBeforeDelete()
    {
        ///DataObject to be Indexed
        $this->flushCache();
        $objects = SearchEngineDataObjectToBeIndexed::get()
            ->filter(array('SearchEngineDataObjectID' => $this->ID));
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

    private $recalculateCount = 0;

    /**
     * Event handler called before writing to the database.
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if($this->Recalculate) {
            //in databas object, make sure onAfterWrite runs!
            $this->forceChange();
        }
    }

    /**
     * Event handler called after writing to the database.
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if($this->Recalculate && $this->recalculateCount < 2) {
            $this->recalculateCount++;
            $this->doSearchEngineIndex();
            $this->write();
        } elseif($this->Recalculate) {
            $this->Recalculate = false;
            $this->write();
        }
    }





















    #############################################
    # DEFINITIONS
    #############################################

    protected function getKey($classNameOnly = false)
    {
        if($classNameOnly) {
            return $this->DataObjectClassName;
        } else {
            return $this->DataObjectID.'_'.$this->DataObjectClassName;
        }
    }



    /**
     * used for caching...
     * @var array
     */
    private static $_search_engine_fields_for_indexing = [];

    public function SearchEngineSourceObjectSortDate($sourceObject = null)
    {
        if(! $sourceObject) {
            $sourceObject = $this->SourceObject();
        }
        if($sourceObject) {
            if($sourceObject->hasMethod('SearchEngineSourceObjectSortDate')) {
                return $sourceObject->SearchEngineSourceObjectSortDate();
            } else {
                $fieldsToCheck = Config::inst()->get(SearchEngineDataObject::class, 'search_engine_date_fields_for_sorting');
                foreach($fieldsToCheck as $field) {
                    if(! empty($sourceObject->$field)) {
                        return $sourceObject->$field;
                    }
                }
            }
        }
    }

    /**
     * returns array like this:
     * 1 => array('Title', 'MenuTitle')
     * 2 => array('Content')
     * @return array
     */
    public function SearchEngineFieldsForIndexing($sourceObject = null)
    {
        $className = $this->getKey(true);
        if(! isset($_search_engine_fields_for_indexing[$className])) {
            $levelFields = [
                1 => [],
                2 => []
            ];
            if(! $sourceObject) {
                $sourceObject = $this->SourceObject();
            }
            if($sourceObject) {
                $levelFields = Config::inst()->get($sourceObject->ClassName, 'search_engine_full_contents_fields_array');
                if (is_array($levelFields) && count($levelFields)) {
                    //do nothing
                } else {
                    $levelOneFieldArray = Config::inst()->get(SearchEngineDataObject::class, 'search_engine_default_level_one_fields');
                    $excludedFieldArray = Config::inst()->get(SearchEngineDataObject::class, 'search_engine_default_excluded_db_fields');
                    $dbArray = $sourceObject->SearchEngineRelFields($sourceObject, 'db');
                    $levelFields = array(SearchEngineKeyword::level_sanitizer(1) => array(), SearchEngineKeyword::level_sanitizer(2) => array());
                    foreach ($dbArray as $field => $type) {
                        //get without brackets ...
                        if (preg_match('/^(\w+)\(/', $type, $match)) {
                            $type = $match[1];
                        }
                        if (is_subclass_of($type, DBString::class)) {
                            if (in_array($field, $excludedFieldArray)) {
                                //do nothing
                            } else {
                                $level = 2;
                                if (in_array($field, $levelOneFieldArray)) {
                                    $level = 1;
                                }
                                $levelFields[$level][] = $field;
                            }
                        }
                    }
                }
            }
            self::$_search_engine_fields_for_indexing[$className] = $levelFields;
        }

        return self::$_search_engine_fields_for_indexing[$className];
    }


    /**
     * used for caching...
     * @var array
     */
    private static $_object_class_name = [];

    /**
     * used for caching...
     * @var array
     */
    private static $_searchable_class_names = null;

    /**
     * returns it like this:
     *
     *     Page => General Page
     *     HomePage => Home Page
     *
     * @return array
     */
    public static function searchable_class_names()
    {
        if(self::$_searchable_class_names === null) {
            $allClasses = ClassInfo::subclassesFor(DataObject::class);
            //specifically include
            $includeClassNames = [];
            //specifically exclude
            $excludeClassNames = [];
            //ones we test for the extension
            $testArray = [];
            //the final list
            $finalClasses = [];

            //check for inclusions
            $include = Config::inst()->get(SearchEngineDataObject::class, 'classes_to_include');
            if(is_array($include) && count($include)) {
                foreach($include as $includeOne) {
                    $includeClassNames = array_merge($includeClassNames, ClassInfo::subclassesFor($includeOne));
                }
            }
            $includeClassNames = array_unique($includeClassNames);

            //if we have inclusions then this is the final list
            if(count($includeClassNames)) {
                $testArray = $includeClassNames;
            } else {
                //lets see which ones are excluded from full list.
                $testArray = $allClasses;
                $exclude = Config::inst()->get(SearchEngineDataObject::class, 'classes_to_exclude');
                if(is_array($exclude) && count($exclude)) {
                    foreach($exclude as $excludeOne) {
                        $excludeClassNames = array_merge($excludeClassNames, ClassInfo::subclassesFor($excludeOne));
                    }
                }
                $excludeClassNames = array_unique($excludeClassNames);
                if(count($excludeClassNames)) {
                    foreach($testArray as $classNameKey => $classNameValue) {
                        foreach($excludeClassNames as $excludeOne) {
                            unset($testArray[$excludeOne]);
                        }
                    }
                }
            }
            foreach ($testArray as $className) {
                //does it have the extension?
                if ($className::has_extension(SearchEngineMakeSearchable::class)) {
                    if (isset(self::$_object_class_name[$className])) {
                        $objectClassName = self::$_object_class_name[$className];
                    } else {
                        $objectClassName = Injector::inst()->get($className)->singular_name();
                        self::$_object_class_name[$className] = $objectClassName;
                    }
                    $finalClasses[$className] = $objectClassName;
                }
            }
            self::$_searchable_class_names = $finalClasses;
        }

        return self::$_searchable_class_names;
    }

    /**
     * @casted variable
     * @return string
     */
    public function getTitle()
    {
        $className = $this->DataObjectClassName;
        if (!class_exists($className)) {
            return 'ERROR - class not found';
        }
        if (isset(self::$_object_class_name[$className])) {
            $objectClassName = self::$_object_class_name[$className];
        } else {
            $objectClassName = Injector::inst()->get($className)->singular_name();
            self::$_object_class_name[$className] = $objectClassName;
        }
        $object = $this->SourceObject();
        if ($object) {
            $objectName = $object->getTitle();
        } else {
            $objectName = 'ERROR: NOT FOUND';
        }

        return $objectClassName.': '.$objectName;
    }


    private static $_source_objects = [];

    /**
     *
     * @return DataObject|null
     */
    public function SourceObject()
    {
        $key = $this->getKey();
        if(! isset(self::$_source_objects[$key])) {
            $className = $this->DataObjectClassName;
            $id = $this->DataObjectID;
            self::$_source_objects[$key] = $className::get()->byID($id);
        }

        return self::$_source_objects[$key];
    }


    private static $_source_objects_exists = [];

    /**
     *
     * @return bool
     */
    public function SourceObjectExists()
    {
        $key = $this->getKey();
        if(! isset(self::$_source_objects_exists[$key])) {
            $className = $this->DataObjectClassName;
            $id = $this->DataObjectID;
            self::$_source_objects_exists[$key] = $className::get()->filter(['ID' => $id])->count() === 1 ? true : false;
        }

        return self::$_source_objects_exists[$key];
    }

    /**
     *
     * @return string
     */
    public function RecordClickLink()
    {
        return 'searchenginerecordclick/add/'.$this->ID.'/';
    }

    private static $_special_sort_group = [];

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
     * @return String
     */
    public function SpecialSortGroup()
    {
        $className = $this->getKey(true);
        if (! isset(self::$_special_sort_group[$className])) {
            self::$_special_sort_group[$className] = '';
            $classGroups = Config::inst()->get(SearchEngineSortByDescriptor::class, 'class_groups');
            if (is_array($classGroups) && count($classGroups)) {
                self::$_special_sort_group[$className] = 'SortGroup999';
                foreach ($classGroups as $level => $classes) {
                    if (in_array($this->DataObjectClassName, $classes)) {
                        self::$_special_sort_group[$className] = 'SortGroup'.$level;
                        break;
                    }
                }
            }
        }

        return self::$_special_sort_group[$className];
    }



















    #############################################
    # CMS
    #############################################


    /**
     * CMS Fields
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
        if($object && $object->hasMethod('CMSEditLink')) {
            $fields->addFieldToTab(
                'Root.Main',
                ReadonlyField::create(
                    'CMSLink',
                    'Open in CMS',
                    DBField::create_field('HTMLText', '<a href="'.$object->CMSEditLink().'" target="_blank">open to edit</a>')
                )
            );
        }
        if($object && ($object->hasMethod('Link'))) {
            $fields->addFieldToTab(
                'Root.Main',
                ReadonlyField::create(
                    'FrontEndLink',
                    'Open on front-end',
                    DBField::create_field('HTMLText', '<a href="'.$object->Link().'" target="_blank">open to view</a>')
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
        return '/admin/searchengine/Sunnysideup-SearchSimpleSmart-Model-SearchEngineDataObject/EditForm/field/Sunnysideup-SearchSimpleSmart-Model-SearchEngineDataObject/item/'.$this->ID.'/edit';
    }















    #####################
    # display
    #####################



    /**
     * @param boolean $moreDetails
     * @return html
     */
    public function getHTMLOutput($moreDetails = false)
    {
        $obj = $this->SourceObject();
        if ($obj) {
            $arrayOfTemplates = $obj->SearchEngineResultsTemplates($moreDetails);
            $cacheKey = 'SearchEngine_'.$obj->ClassName.'_'.abs($obj->ID).'_'.($moreDetails ? 'MOREDETAILS' : 'NOMOREDETAILS');

            $cache = Injector::inst()->get(CacheInterface::class . '.SearchEngine');

            $templateRender = null;
            if($cache->has($cacheKey)) {
                $templateRender = $cache->get($cacheKey);
            }
            if ($templateRender) {
                $templateRender = unserialize($templateRender);
            } else {
                $templateRender = $obj->renderWith(($arrayOfTemplates));
                $cache->set($cacheKey, serialize($templateRender));
            }
            return $templateRender;
        }
    }

    /**
     * @return html
     */
    public function getHTMLOutputMoreDetails()
    {
        return $this->getHTMLOutput(true);
    }


    /**
     * returns a template for formatting the object
     * in the search results.
     *
     * @param boolean $moreDetails
     *
     * @return array
     */
    public function SearchEngineResultsTemplates($sourceObject = null, $moreDetails = false)
    {
        if(! $sourceObject) {
            $sourceObject = $this->SourceObject();
        }
        if($sourceObject) {
            if ($sourceObject->hasMethod('SearchEngineResultsTemplatesProvider')) {
                return $sourceObject->SearchEngineResultsTemplatesProvider($moreDetails);
            } else {
                $template = Config::inst()->get($sourceObject->ClassName, 'search_engine_results_templates');
                if ($template) {
                    if ($moreDetails) {
                        return array($template.'_MoreDetails', $template);
                    } else {
                        return array($template);
                    }
                } else {
                    $arrayOfTemplates = [];
                    $parentClasses = class_parents($sourceObject);
                    $firstTemplate = 'SearchEngineResultItem_'.$sourceObject->ClassName;
                    if ($moreDetails) {
                        $arrayOfTemplates = array($firstTemplate.'_MoreDetails', $firstTemplate);
                    } else {
                        $arrayOfTemplates = array($firstTemplate);
                    }
                    foreach ($parentClasses as $parent) {
                        if ($parent == DataObject::class) {
                            break;
                        }
                        if ($moreDetails) {
                            $arrayOfTemplates[]= 'SearchEngineResultItem_'.$parent.'_MoreDetails';
                        }
                        $arrayOfTemplates[]= 'SearchEngineResultItem_'.$parent;
                    }
                    if ($moreDetails) {
                        $arrayOfTemplates[]= 'SearchEngineResultItem_DataObject_MoreDetails';
                    }
                    $arrayOfTemplates[] = 'SearchEngineResultItem_DataObject';

                    return $arrayOfTemplates;
                }
            }
        }
        return [];
    }



    public function SearchEngineFieldsToBeIndexedHumanReadable($sourceObject = null, $includeExample = false)
    {
        $str = 'ERROR';
        if(! $sourceObject) {
            $sourceObject = $this->SourceObject();
        }
        if($sourceObject) {
            $levels = $sourceObject->SearchEngineFieldsForIndexing();
            if (is_array($levels)) {
                ksort($levels);
                $fieldLabels = $sourceObject->fieldLabels();
                $str = '<ul>';
                foreach ($levels as $level => $fieldArray) {
                    $str .= '<li><strong>'.$level.'</strong><ul>';
                    foreach ($fieldArray as $field) {
                        if (isset($fieldLabels[$field])) {
                            $title = $fieldLabels[$field].' ['. $field .']';
                        } else {
                            $title = $field;
                        }
                        if ($includeExample) {
                            $fields = explode('.', $field);
                            $data = ' '.$sourceObject->SearchEngineRelObject($sourceObject, $fields).' ';
                            $str .= '<li> - <strong>'.$title.'</strong> <em>'.$data.'</em></li>';
                        } else {
                            $str .= '<li> - '.$title.'</li>';
                        }
                    }
                    $str .= '</ul></li>';
                    //$str .= '<li>results in: <em>'.$this->SearchEngineFullContentForIndexing()[$level].'</em></li></ol>';
                }
                $str .= '</ul>';
            } else {
                $str = _t('SearchEngineDataObject.NO_FIELDS', '<p>No fields are listed for indexing.</p>');
            }
        }

        return $str;
    }











    #####################
    # do stuff
    #####################

    private $timeMeasure = [];

    /**
     * deletes cached search results
     * sets stage to LIVE
     * indexes the current object.
     * @param $searchEngineDataObject SearchEngineDataObject
     * @param $withModeChange do everything necessary for indexings.
     *                        Setting this to false means the stage will not be set
     *                        and the cache will not be cleared.
     */
    public function doSearchEngineIndex($sourceObject = null, $withModeChange = true, $timeMeasure = false)
    {
        if($timeMeasure) {
            $this->timeMeasure = [];
        }
        if(! $sourceObject) {
            $sourceObject = $this->SourceObject();
        }
        if($sourceObject) {
            if($withModeChange) {
                SearchEngineDataObject::start_indexing_mode();
            }

            //add date!
            $this->DataObjectDate = $this->SearchEngineSourceObjectSortDate($sourceObject);
            $this->write();

            if($timeMeasure) {
                $startTime = microtime(true);
            }

            //get the full content
            $fullContentArray = $this->SearchEngineFullContentForIndexingBuild($sourceObject);
            if($timeMeasure) {
                $endTime = microtime(true);
                $this->timeMeasure['FullContentBuild'] = $endTime - $startTime;
                $startTime = microtime(true);
            }

            //add the full content
            SearchEngineFullContent::add_data_object_array($this, $fullContentArray);


            if($timeMeasure) {
                $endTime = microtime(true);
                $this->timeMeasure['AddContent'] = $endTime - $startTime;
            }
            if($withModeChange) {
                SearchEngineDataObject::end_indexing_mode();
            }
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
     * @return array
     */
    public function SearchEngineFullContentForIndexingBuild($sourceObject = null)
    {
        $finalArray = [];
        if(! $sourceObject) {
            $sourceObject = $this->SourceObject();
        }
        if($sourceObject) {
            if ($sourceObject->hasMethod('SearchEngineFullContentForIndexingProvider')) {
                $finalArray = $sourceObject->SearchEngineFullContentForIndexingProvider();
            } else {
                $levels = Config::inst()->get($sourceObject->ClassName, 'search_engine_full_contents_fields_array');
                if (is_array($levels)) {
                    //do nothing
                } else {
                    $levels = $sourceObject->SearchEngineFieldsForIndexing();
                }
                if (is_array($levels) && count($levels)) {
                    foreach ($levels as $level => $fieldArray) {
                        $level = SearchEngineKeyword::level_sanitizer($level);
                        $finalArray[$level] = '';
                        if (is_array($fieldArray) && count($fieldArray)) {
                            foreach ($fieldArray as $field) {
                                $fields = explode('.', $field);
                                $finalArray[$level] .= ' '.$sourceObject->searchEngineRelObject($sourceObject, $fields).' ';
                            }
                        }
                    }
                }
            }
        }

        return $finalArray;
    }


}
