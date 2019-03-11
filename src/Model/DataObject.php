<?php

namespace Sunnysideup\SearchSimpleSmart\Model;

use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineFullContent;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\Assets\Folder;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Extensions\SearchEngineMakeSearchable;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\SearchSimpleSmart\Abstractions\SearchEngineSortByDescriptor;

/**
 * List of dataobjects that are indexed.
 */

class SearchEngineDataObject extends DataObject
{

    /**
     * @var string
     */
    private static $singular_name = "Searchable Item";
    public function i18n_singular_name()
    {
        return self::$singular_name;
    }

    /**
     * @var string
     */
    private static $plural_name = "Searchable Items";
    public function i18n_plural_name()
    {
        return self::$plural_name;
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
        "ListedForIndexing" => "Boolean",
        "HTMLOutput" => "HTMLText",
        "HTMLOutputMoreDetails" => "HTMLText"
    );

    /**
     * @var array
     */
    private static $summary_fields = array(
        "Title" => "Title",
        "LastEdited.Nice" => "Last Updated"
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

    /**
     *
     * @var Array
     */
    private static $classes_to_exclude = array(
        ErrorPage::class,
        "Image_Cached",
        VirtualPage::class,
        RedirectorPage::class,
        Folder::class
    );

    private static $_find_or_make_items = array();

    /**
     *
     * @param DataObject $obj
     * @param bool $doNotMake
     * @return SearchEngineDataObject | false
     */
    public static function find_or_make($obj, $doNotMake = false)
    {
        if (!isset(self::$_find_or_make_items[$obj->ClassName."_".$obj->ID])) {
            if ($obj instanceof DataObject) {
                $fieldArray = array(
                    "DataObjectClassName" => $obj->ClassName,
                    "DataObjectID" => $obj->ID
                );
                $item = SearchEngineDataObject::get()
                    ->filter($fieldArray)
                    ->first();
                if ($item || $doNotMake) {
                    //do nothing;
                } else {
                    $item = SearchEngineDataObject::create($fieldArray);
                    $item->write();
                }
                self::$_find_or_make_items[$obj->ClassName."_".$obj->ID] = $item;
            } else {
                user_error("DataObject expected, instead, the following was provided: ".var_dump($obj));
            }
        }
        return self::$_find_or_make_items[$obj->ClassName."_".$obj->ID];
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
        $array = array();

        /**
          * ### @@@@ START REPLACEMENT @@@@ ###
          * WHY: upgrade to SS4
          * OLD: $className (case sensitive)
          * NEW: $className (COMPLEX)
          * EXP: Check if the class name can still be used as such
          * ### @@@@ STOP REPLACEMENT @@@@ ###
          */
        foreach ($classes as $className) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
            if (!in_array($className, Config::inst()->get(SearchEngineDataObject::class, "classes_to_exclude"))) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                if ($className::has_extension(SearchEngineMakeSearchable::class)) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                    if (isset(self::$_object_class_name[$className])) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                        $objectClassName = $_object_class_name[$className];
                    } else {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
                        $objectClassName = Injector::inst()->get($className)->singular_name();
                    }

                    /**
                      * ### @@@@ START REPLACEMENT @@@@ ###
                      * WHY: upgrade to SS4
                      * OLD: $className (case sensitive)
                      * NEW: $className (COMPLEX)
                      * EXP: Check if the class name can still be used as such
                      * ### @@@@ STOP REPLACEMENT @@@@ ###
                      */
                    $array[$className] = $objectClassName;
                }
            }
        }
        return $array;
    }

    /**
     * @return bool
     */
    public function canDelete($member = null, $context = [])
    {
        return true;
    }

    /**
     * used for caching...
     * @var array
     */
    private static $_object_class_name = array();

    /**
     * @casted variable
     * @return string
     */
    public function getTitle()
    {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
        $className = $this->DataObjectClassName;

        /**
          * ### @@@@ START REPLACEMENT @@@@ ###
          * WHY: upgrade to SS4
          * OLD: $className (case sensitive)
          * NEW: $className (COMPLEX)
          * EXP: Check if the class name can still be used as such
          * ### @@@@ STOP REPLACEMENT @@@@ ###
          */
        if (!class_exists($className)) {
            return "ERROR - class not found";
        }

        /**
          * ### @@@@ START REPLACEMENT @@@@ ###
          * WHY: upgrade to SS4
          * OLD: $className (case sensitive)
          * NEW: $className (COMPLEX)
          * EXP: Check if the class name can still be used as such
          * ### @@@@ STOP REPLACEMENT @@@@ ###
          */
        if (isset(self::$_object_class_name[$className])) {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
            $objectClassName = self::$_object_class_name[$className];
        } else {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
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
     * @casted variable
     * @return bool
     */
    public function getListedForIndexing()
    {
        return $this->SearchEngineDataObjectToBeIndexedID ? true : false;
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

            /**
              * ### @@@@ START REPLACEMENT @@@@ ###
              * WHY: upgrade to SS4
              * OLD: Cache::factory( (case sensitive)
              * NEW: SilverStripe\Core\Injector\Injector::inst()->get(Psr\SimpleCache\CacheInterface::class .  (COMPLEX)
              * EXP: Check cache implementation - see: https://docs.silverstripe.org/en/4/changelogs/4.0.0#cache
              * ### @@@@ STOP REPLACEMENT @@@@ ###
              */
            $cache = SS_SilverStripe\Core\Injector\Injector::inst()->get(Psr\SimpleCache\CacheInterface::class . "SearchEngine");

            /**
              * ### @@@@ START REPLACEMENT @@@@ ###
              * WHY: upgrade to SS4
              * OLD: $cache->load( (case sensitive)
              * NEW: $cache->has( (COMPLEX)
              * EXP: See: https://docs.silverstripe.org/en/4/changelogs/4.0.0#cache
              * ### @@@@ STOP REPLACEMENT @@@@ ###
              */
            $templateRender = $cache->has($cacheKey);
            if ($templateRender) {
                $templateRender = unserialize($templateRender);
            } else {
                $templateRender = $obj->renderWith(($arrayOfTemplates));

                /**
                  * ### @@@@ START REPLACEMENT @@@@ ###
                  * WHY: upgrade to SS4
                  * OLD: $cache->save( (case sensitive)
                  * NEW: $cache->set( (COMPLEX)
                  * EXP: Cache key and value need to be swapped. Put key first. See: https://docs.silverstripe.org/en/4/changelogs/4.0.0#cache
                  * ### @@@@ STOP REPLACEMENT @@@@ ###
                  */
                $cache->set(serialize($templateRender), $cacheKey);
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
        $item->delete();
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        SearchEngineKeyword::export_keyword_list();
    }

    /**
     * make sure all the references are deleted as well
     *
     */
    public function onBeforeDelete()
    {

        ///DataObject to be Indexed
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
    }

    /**
     *
     * @return DataObject | null
     */
    public function SourceObject()
    {

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD: $className (case sensitive)
  * NEW: $className (COMPLEX)
  * EXP: Check if the class name can still be used as such
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
        $className = $this->DataObjectClassName;
        $id = $this->DataObjectID;

        /**
          * ### @@@@ START REPLACEMENT @@@@ ###
          * WHY: upgrade to SS4
          * OLD: $className (case sensitive)
          * NEW: $className (COMPLEX)
          * EXP: Check if the class name can still be used as such
          * ### @@@@ STOP REPLACEMENT @@@@ ###
          */
        return $className::get()->byID($id);
    }

    /**
     *
     * @return string
     */
    public function RecordClickLink()
    {
        return "searchenginerecordclick/add/".$this->ID."/";
    }

    private static $_special_sort_group = array();

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
}
