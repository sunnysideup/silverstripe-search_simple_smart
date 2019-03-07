<?php

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
        "SearchEngineDataObjectToBeIndexed" => "SearchEngineDataObjectToBeIndexed",
        "SearchEngineFullContents" => "SearchEngineFullContent"
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
        "SearchEngineKeywords_Level1" => "SearchEngineKeyword",
    );

    /**
     * @var array
     */
    private static $many_many = array(
        "SearchEngineKeywords_Level2" => "SearchEngineKeyword",
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
        "ErrorPage",
        "Image_Cached",
        "VirtualPage",
        "RedirectorPage",
        "Folder"
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
        $classes = ClassInfo::subclassesFor("DataObject");
        $array = array();
        foreach ($classes as $className) {
            if (!in_array($className, Config::inst()->get("SearchEngineDataObject", "classes_to_exclude"))) {
                if ($className::has_extension("SearchEngineMakeSearchable")) {
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
     * @return bool
     */
    public function canDelete($member = null)
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
            $cache = SS_Cache::factory("SearchEngine");
            $templateRender = $cache->load($cacheKey);
            if ($templateRender) {
                $templateRender = unserialize($templateRender);
            } else {
                $templateRender = $obj->renderWith(($arrayOfTemplates));
                $cache->save(serialize($templateRender), $cacheKey);
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
        $className = $this->DataObjectClassName;
        $id = $this->DataObjectID;
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
            $classGroups = Config::inst()->get("SearchEngineSortByDescriptor", "class_groups");
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
