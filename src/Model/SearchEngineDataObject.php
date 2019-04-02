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

    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SearchEngineDataObject';

    /**
     * @var string
     */
    private static $singular_name = "Searchable Item";
    public function i18n_singular_name()
    {
        return $this->Config()->get('singular_name');
    }

    /**
     * @var string
     */
    private static $plural_name = "Searchable Items";
    public function i18n_plural_name()
    {
        return $this->Config()->get('plural_name');
    }

    /**
     * @var array
     */
    private static $db = array(
        "DataObjectClassName" => "Varchar(150)",
        "DataObjectID" => "Int"
    );

    /**
     * @var array
     */
    private static $has_many = array(
        "SearchEngineDataObjectToBeIndexed" => SearchEngineDataObjectToBeIndexed::class,
        "SearchEngineFullContents" => SearchEngineFullContent::class
    );

    //should work but does not
    //private static $belongs_many_many = array(
    //	"SearchEngineKeywords_Level1" => "SearchEngineKeyword.SearchEngineDataObjects_Level1",
    //	"SearchEngineKeywords_Level2" => "SearchEngineKeyword.SearchEngineDataObjects_Level2",
    //	"SearchEngineKeywords_Level3" => "SearchEngineKeyword.SearchEngineDataObjects_Level3"
    //);

    /**
     * @var array
     */
    private static $belongs_many_many = array(
        "SearchEngineKeywords_Level1" => SearchEngineKeyword::class,
    );

    /**
     * @var array
     */
    private static $many_many = array(
        "SearchEngineKeywords_Level2" => SearchEngineKeyword::class,
    );

    /**
     * @var array
     */
    private static $many_many_extraFields = array(
        "SearchEngineKeywords_Level2" => array("Count" => "Int"),
        //"SearchEngineDataObjects_Level3" => array("Count" => "Int")
    );

    /**
     * @var array
     */
    private static $indexes = array(
        "DataObjectClassName" => true,
        "DataObjectID" => true
    );

    /**
     * @var array
     */
    private static $default_sort = array(
        "DataObjectClassName" => "ASC",
        "DataObjectID" => "ASC"
    );

    /**
     * @var array
     */
    private static $required_fields = array(
        "DataObjectClassName" => true,
        "DataObjectID" => true
    );

    /**
     * @var array
     */
    private static $casting = array(
        "Title" => "Varchar",
        "HTMLOutput" => "HTMLText",
        "HTMLOutputMoreDetails" => "HTMLText"
    );

    /**
     * @var array
     */
    private static $summary_fields = array(
        "Title" => "Title",
        "LastEdited.Nice" => "Last Updated",
        "SearchEngineDataObjectToBeIndexed.Count" => 'Indexed Times',
        "SearchEngineKeywords_Level1.Count" => 'Level1 Keywords',
        "SearchEngineKeywords_Level2.Count" => 'Level2 Keywords'
    );

    /**
     * @var array
     */
    private static $searchable_fields = array(
        "DataObjectClassName" => "PartialMatchFilter"
    );

    /**
     * @var array
     */
    private static $field_labels = array(
        "DataObjectClassName" => "Object",
        "DataObjectID" => "ID",
        "SearchEngineDataObjectToBeIndexed" => "Listed for indexing"
    );


    private static $_original_mode = null;

    public static function start_indexing_mode()
    {
        SearchEngineSearchRecord::flush();
        self::$_original_mode = Versioned::get_stage();
        Versioned::set_stage("Live");
    }

    public static function end_indexing_mode()
    {
        Versioned::set_stage(self::$_original_mode);
    }

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
        return false;
    }

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canDelete($member = null, $context = [])
    {
        return parent::canDelete() && Permission::check("SEARCH_ENGINE_ADMIN");
    }

    /**
     * @param Member $member
     * @param array $context Additional context-specific data which might
     * affect whether (or where) this object could be created.
     * @return boolean
     */
    public function canView($member = null, $context = [])
    {
        return parent::canView() && Permission::check("SEARCH_ENGINE_ADMIN");
    }

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
                    "DataObjectClassName" => $obj->ClassName,
                    "DataObjectID" => $obj->ID
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
            user_error("DataObject expected, instead, the following was provided: ".var_dump($obj));
        }
    }

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
        $classes = ClassInfo::subclassesFor(DataObject::class);
        $array = [];
        foreach ($classes as $className) {
            if (!in_array($className, Config::inst()->get(SearchEngineDataObject::class, "classes_to_exclude"))) {
                if ($className::has_extension(SearchEngineMakeSearchable::class)) {
                    if (isset(self::$_object_class_name[$className])) {
                        $objectClassName = $_object_class_name[$className];
                    } else {
                        $objectClassName = Injector::inst()->get($className)->singular_name();
                    }
                    $array[$className] = $objectClassName;
                }
            }
        }
        return $array;
    }

    /**
     * used for caching...
     * @var array
     */
    private static $_object_class_name = [];

    /**
     * @casted variable
     * @return string
     */
    public function getTitle()
    {
        $className = $this->DataObjectClassName;
        if (!class_exists($className)) {
            return "ERROR - class not found";
        }
        if (isset(self::$_object_class_name[$className])) {
            $objectClassName = self::$_object_class_name[$className];
        } else {
            $objectClassName = Injector::inst()->get($className)->singular_name();
        }
        $object = $this->SourceObject();
        if ($object) {
            $objectName = $object->getTitle();
        } else {
            $objectName = "ERROR: NOT FOUND";
        }

        return $objectClassName.": ".$objectName;
    }



    /**
     * @param boolean $moreDetails
     * @return html
     */
    public function getHTMLOutput($moreDetails = false)
    {
        $obj = $this->SourceObject();
        if ($obj) {
            $arrayOfTemplates = $obj->SearchEngineResultsTemplates($moreDetails);
            $cacheKey = 'SearchEngine_'.$obj->ClassName."_".abs($obj->ID)."_".($moreDetails ? "MOREDETAILS" : "NOMOREDETAILS");

            $cache = Injector::inst()->get(CacheInterface::class . ".SearchEngine");

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
            ->filter(array("SearchEngineDataObjectID" => $this->ID));
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
     *
     * @return DataObject|null
     */
    public function SourceObject()
    {
        $className = $this->DataObjectClassName;
        $id = $this->DataObjectID;

        return $className::get()->byID($id);
    }

    /**
     *
     * @return DataObject|null
     */
    public function SourceObjectExists()
    {
        $className = $this->DataObjectClassName;
        $id = $this->DataObjectID;

        return $className::get()->filter(['ID' => $id])->count() === 1 ? true : false;
    }

    /**
     *
     * @return string
     */
    public function RecordClickLink()
    {
        return "searchenginerecordclick/add/".$this->ID."/";
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
        if (!isset(self::$_special_sort_group[$this->DataObjectClassName])) {
            self::$_special_sort_group[$this->DataObjectClassName] = "";
            $classGroups = Config::inst()->get(SearchEngineSortByDescriptor::class, "class_groups");
            if (is_array($classGroups) && count($classGroups)) {
                self::$_special_sort_group[$this->DataObjectClassName] = "SortGroup999";
                foreach ($classGroups as $level => $classes) {
                    if (in_array($this->DataObjectClassName, $classes)) {
                        self::$_special_sort_group[$this->DataObjectClassName] = "SortGroup".$level;
                        break;
                    }
                }
            }
        }
        return self::$_special_sort_group[$this->DataObjectClassName];
    }


    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
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
                    DBField::create_field('HTMLText', '<a href="'.$object->CMSEditLink().'" target="_blank">open actual object in the cms</a>')
                )
            );
        }
        if($object && $object->hasMethod('Link')) {
            $fields->addFieldToTab(
                'Root.Main',
                ReadonlyField::create(
                    'FrontEndLink',
                    'Open on site',
                    DBField::create_field('HTMLText', '<a href="'.$object->Link().'" target="_blank">open on site</a>')
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

}
