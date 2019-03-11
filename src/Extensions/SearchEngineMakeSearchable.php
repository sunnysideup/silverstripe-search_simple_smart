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
        "ReportClass",
        "CanViewType",
        "ExtraMeta",
        "CanEditType",
        "Password"
    );

    /**
     * deletes cached search results
     * sets stage to LIVE
     * indexes the current object.
     */
    public function searchEngineIndex()
    {
        //last check...
        if ($this->SearchEngineExcludeFromIndex()) {
            //do nothing
        } else {
            //clear search history
            SearchEngineSearchRecord::flush();
            $originalMode = Versioned::get_reading_mode();
            Versioned::set_stage("Live");
            $item = SearchEngineDataObject::find_or_make($this->owner);
            $fullContentArray = $this->owner->SearchEngineFullContentForIndexing();
            SearchEngineFullContent::add_data_object_array($item, $fullContentArray);
            Versioned::set_reading_mode($originalMode);
        }
    }

    /**
     * Indexed Full Content Data
     * @return DataList
     */
    public function SearchEngineDataObjectFullContent()
    {
        $item = SearchEngineDataObject::find_or_make($this->owner);

        return $item->SearchEngineFullContents();
    }

    /**
     * Indexed Keywords
     * @return DataList
     */
    public function SearchEngineKeywordDataObjectMatches($level = 1)
    {
        $item = SearchEngineDataObject::find_or_make($this->owner);
        $field = "SearchEngineKeywords_Level".$level;

        return $item->$field();
    }

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
        if ($this->owner->hasMethod("SearchEngineResultsTemplatesProvider")) {
            return $this->owner->SearchEngineResultsTemplatesProvider($moreDetails);
        } else {
            $template = Config::inst()->get($this->owner->ClassName, "search_engine_results_templates");
            if ($template) {
                if ($moreDetails) {
                    return array($template."_MoreDetails", $template);
                } else {
                    return array($template);
                }
            } else {
                $arrayOfTemplates = [];
                $parentClasses = class_parents($this->owner);
                $firstTemplate = "SearchEngineResultItem_".$this->owner->ClassName;
                if ($moreDetails) {
                    $arrayOfTemplates = array($firstTemplate."_MoreDetails", $firstTemplate);
                } else {
                    $arrayOfTemplates = array($firstTemplate);
                }
                foreach ($parentClasses as $parent) {
                    if ($parent == DataObject::class) {
                        break;
                    }
                    if ($moreDetails) {
                        $arrayOfTemplates[]= "SearchEngineResultItem_".$parent."_MoreDetails";
                    }
                    $arrayOfTemplates[]= "SearchEngineResultItem_".$parent;
                }
                if ($moreDetails) {
                    $arrayOfTemplates[]= "SearchEngineResultItem_DataObject_MoreDetails";
                }
                $arrayOfTemplates[] = "SearchEngineResultItem_DataObject";

                return $arrayOfTemplates;
            }
        }
    }

    /**
     * returns a list of classnames + IDs
     * that also need to be updated when this object is updated:
     *     return array(
     *          [0] => array("ClassName" => MyOtherClassname, "ID" => 123),
     *          [0] => array("ClassName" => FooClassName, "ID" => 122)
     *      )
     * @return array | null
     */
    public function SearchEngineAlsoTrigger()
    {
        if ($this->owner->hasMethod("SearchEngineAlsoTriggerProvider")) {
            return $this->owner->SearchEngineAlsoTriggerProvider();
        }
    }

    public function updateCMSFields(FieldList $fields)
    {
        if (SiteConfig::current_site_config()->SearchEngineDebug || Permission::check("SEARCH_ENGINE_ADMIN")) {
            if ($fields->fieldByName("Root")) {
                $fields->findOrMakeTab("Root.Main");
                $fields->removeFieldFromTab("Root.Main", "SearchEngineDataObjectID");
                if (!$this->owner->hasMethod("getSettingsFields")) {
                    return $this->updateSettingsFields($fields);
                }
            }
        }
    }

    public function updateSettingsFields(FieldList $fields)
    {
        if (SiteConfig::current_site_config()->SearchEngineDebug || Permission::check("SEARCH_ENGINE_ADMIN")) {
            if ($this->SearchEngineExcludeFromIndex()) {
                return;
            }
            $item = SearchEngineDataObject::find_or_make($this->owner);
            $toBeIndexed = SearchEngineDataObjectToBeIndexed::get()->filter(array("SearchEngineDataObjectID" => $item->ID, "Completed" => 0))->count() ? "Yes" : "No";
            $fields->addFieldToTab("Root.SearchEngine", $lastIndexed = new ReadonlyField("LastIndexed", "Approximately Last Index", $this->owner->LastEdited));
            $fields->addFieldToTab("Root.SearchEngine", $toBeIndexed = new ReadonlyField("ToBeIndexed", "On the list to be indexed", $toBeIndexed));
            $config = GridFieldConfig_RecordEditor::create()->removeComponentsByType(GridFieldAddNewButton::class);
            $fields->addFieldToTab(
                "Root.SearchEngine",
                $itemField = new LiteralField(
                    "Levels",
                    $this->owner->SearchEngineFieldsToBeIndexedHumanReadable(true)
                )
            );
            $fields->addFieldToTab(
                "Root.SearchEngine",
                new GridField(
                    "SearchEngineKeywords_Level1",
                    "Keywords Level 1",
                    $this->owner->SearchEngineKeywordDataObjectMatches(1),
                    $config
                )
            );
            $fields->addFieldToTab(
                "Root.SearchEngine",
                new GridField(
                    "SearchEngineKeywords_Level2",
                    "Keywords Level 2",
                    $this->owner->SearchEngineKeywordDataObjectMatches(2),
                    $config
                )
            );
            $fields->addFieldToTab(
                "Root.SearchEngine",
                new GridField(
                    SearchEngineFullContent::class,
                    "Full Content",
                    $this->owner->SearchEngineDataObjectFullContent(),
                    $config
                )
            );
            $fields->addFieldToTab(
                "Root.SearchEngine",
                $itemField = new GridField(
                    SearchEngineDataObject::class,
                    "Searchable Item",
                    SearchEngineDataObject::get()->filter(array("DataObjectClassName" => $this->owner->ClassName, "DataObjectID" => $this->owner->ID)),
                    $config
                )
            );
        }
    }

    public function SearchEngineFieldsToBeIndexedHumanReadable($includeExample = false)
    {
        $levels = $this->owner->SearchEngineFieldsForIndexing();
        if (is_array($levels)) {
            ksort($levels);
            $fieldLabels = $this->owner->fieldLabels();
            $str = "<ul>";
            foreach ($levels as $level => $fieldArray) {
                $str .= "<li><strong>$level</strong><ul>";
                foreach ($fieldArray as $field) {
                    if (isset($fieldLabels[$field])) {
                        $title = $fieldLabels[$field]." [". $field ."]";
                    } else {
                        $title = "$field";
                    }
                    if ($includeExample) {
                        $fields = explode(".", $field);
                        $data = " ".$this->searchEngineRelObject($this->owner, $fields);
                        $str .= "<li> - <strong>$title</strong> <em>".$data."</em></li>";
                    } else {
                        $str .= "<li> - $title</li>";
                    }
                }
                $str .= "</ul></li>";
                //$str .= "<li>results in: <em>".$this->SearchEngineFullContentForIndexing()[$level]."</em></li></ol>";
            }
            $str .= "</ul>";
        } else {
            $str = _t("MakeSearchable.NO_FIElDS", "<p>No fields are listed for indexing.</p>");
        }
        return $str;
    }

    /**
     *
     */
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
                $className = $details["ClassName"];
                $id = $details["ID"];
                $obj = $className::get()->byID($id);
                if($obj->hasExtension(Versioned::class)) {
                    if ($obj->isPublished()) {
                        $obj->writeToStage('Live');
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
            if ($item && $this->_onAfterWriteCount[$this->owner->ID]++ < 2) {
                $item->write();
                DB::query("UPDATE \"SearchEngineDataObject\" SET LastEdited = NOW() WHERE ID = ".(intval($item->ID)-0).";");
                SearchEngineDataObjectToBeIndexed::add($item);
            }
        }
    }

    /**
     * @return boolean
     */
    public function SearchEngineExcludeFromIndex()
    {
        $exclude = false;
        $alwaysExclude = Config::inst()->get(SearchEngineDataObject::class, "classes_to_exclude");
        if (in_array($this->owner->ClassName, $alwaysExclude) || is_subclass_of($this->owner->ClassName, $alwaysExclude)) {
            $exclude = true;
        } else {
            if ($this->owner->hasMethod("SearchEngineExcludeFromIndexProvider")) {
                $exclude = $this->owner->SearchEngineExcludeFromIndexProvider();
            } else {
                $exclude = Config::inst()->get($this->owner->ClassName, "search_engine_exclude_from_index");
            }
            //special case SiteTree
            if ($this->owner instanceof SiteTree) {
                if ($this->owner->ShowInSearch && $this->owner->IsPublished()) {
                    //do nothing
                } else {
                    $exclude = true;
                }
            }
        }
        //if it is to be excluded then remove from
        //search index.
        if ($exclude) {
            $exclude = true;
            $this->SearchEngineDeleteFromIndexing();
        }
        return $exclude;
    }

    public function SearchEngineDeleteFromIndexing()
    {
        if ($item = SearchEngineDataObject::find_or_make($this->owner, true)) {
            if ($item && $item->exists()) {
                $item->delete();
            }
        }
    }

    /**
     * returns array like this:
     * 1 => array("Title", "MenuTitle")
     * 2 => array("Content")
     * @return array
     */
    public function SearchEngineFieldsForIndexing()
    {
        $levelFields = Config::inst()->get($this->owner->ClassName, "search_engine_full_contents_fields_array");
        if (is_array($levelFields) && count($levelFields)) {
            //do nothing
        } else {
            $levelOneFieldArray = Config::inst()->get(SearchEngineMakeSearchable::class, "search_engine_default_level_one_fields");
            $excludedFieldArray = Config::inst()->get(SearchEngineMakeSearchable::class, "search_engine_default_excluded_db_fields");
            $dbArray = $this->searchEngineRelFields($this->owner, "db");
            $levelFields = array(SearchEngineKeyword::level_sanitizer(1) => array(), SearchEngineKeyword::level_sanitizer(2) => array());
            foreach ($dbArray as $field => $type) {
                //get without brackets ...
                if (preg_match('/^(\w+)\(/', $type, $match)) {
                    $type = $match[1];
                }
                if (is_subclass_of($type, 'StringField')) {
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
        return $levelFields;
    }

    /**
     * returns a full-text version of an object like this:
     * array(
     *   1 => "bla",
     *   2 => "foo",
     * );
     * where 1 and 2 are the levels of importance of each string.
     *
     * @return array
     */
    public function SearchEngineFullContentForIndexing()
    {
        $finalArray = [];
        if ($this->owner->hasMethod("SearchEngineFullContentForIndexingProvider")) {
            $finalArray = $this->owner->SearchEngineFullContentForIndexingProvider();
        } else {
            $levels = Config::inst()->get($this->owner->ClassName, "search_engine_full_contents_fields_array");
            if (is_array($levels)) {
                //do nothing
            } else {
                $levels = $this->owner->SearchEngineFieldsForIndexing();
            }
            if (is_array($levels) && count($levels)) {
                foreach ($levels as $level => $fieldArray) {
                    $level = SearchEngineKeyword::level_sanitizer($level);
                    $finalArray[$level] = "";
                    if (is_array($fieldArray) && count($fieldArray)) {
                        foreach ($fieldArray as $field) {
                            $fields = explode(".", $field);
                            $finalArray[$level] .= " ".$this->searchEngineRelObject($this->owner, $fields);
                        }
                    }
                }
            }
        }
        return $finalArray;
    }


    /**
     * @param DataObject $object
     * @param array $fields array of TWO items.  The first specifies the relation,
     *                      the second one the method that should be run on the relation (if any)
     *                      you can also specific more relations ...
     *
     * @return array
     */
    private function searchEngineRelObject($object, $fields, $str = "")
    {
        if (count($fields) == 0 || !is_array($fields)) {
            $str .= $object->getTitle();
        } else {
            $fieldCount = count($fields);
            $possibleMethod = $fields[0];
            if(substr($possibleMethod, 0, 3) === 'get' &&  $object->hasMethod($possibleMethod)) {
                if ($fieldCount == 1) {
                    $str .= $object->$possibleMethod()." ";
                } elseif ($fieldCount == 2) {
                    $secondMethod = $fields[1];
                    $str .= $this->owner->$possibleMethod()->$secondMethod()." ";
                }
            }
            $dbArray = $this->searchEngineRelFields($object, "db");
            //db field
            if (isset($dbArray[$fields[0]])) {
                if ($fieldCount == 1) {
                    $str .= $object->$fields[0]." ";
                } elseif ($fieldCount == 2) {
                    $method = $fields[1];
                    $str .= $this->owner->dbObject($fields[0])->$method()." ";
                }
            }
            //has one relation
            else {
                $method = array_shift($fields);
                $hasOneArray = array_merge(
                    $this->searchEngineRelFields($object, "has_one"),
                    $this->searchEngineRelFields($object, "belongs_to")
                );
                //has_one relation
                if (isset($hasOneArray[$method])) {
                    $foreignObject = $this->owner->$method();
                    $str .= $this->searchEngineRelObject($foreignObject, $fields)." ";
                }
                //many relation
                else {
                    $manyArray = array_merge(
                        $this->searchEngineRelFields($object, "has_many"),
                        $this->searchEngineRelFields($object, "many_many"),
                        $this->searchEngineRelFields($object, "belongs_many_many")
                    );
                    if (isset($manyArray[$method])) {
                        $foreignObjects = $this->owner->$method()->limit(100);
                        foreach ($foreignObjects as $foreignObject) {
                            $str .= $this->searchEngineRelObject($foreignObject, $fields)." ";
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
    private function searchEngineRelFields($object, $relType)
    {
        if (!isset($this->_array_of_relations[$object->ClassName])) {
            $this->_array_of_relations[$object->ClassName] = [];
        }
        if (!isset($this->_array_of_relations[$object->ClassName][$relType])) {
            $this->_array_of_relations[$object->ClassName][$relType] = $object->stat($relType);
        }
        return $this->_array_of_relations[$object->ClassName][$relType];
    }
}
