<?php

namespace Sunnysideup\SearchSimpleSmart\Api;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBString;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\SearchSimpleSmart\Extensions\SearchEngineMakeSearchable;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;
use Sunnysideup\SearchSimpleSmart\Api\SearchEngineMakeSearchableApi;

class SearchEngineDataObjectApi
{
    /**
     * used for caching...
     * @var array
     */
    private static $_searchable_class_names = [];

    private static $_original_mode = null;

    /**
     * @param DataObject $obj
     * @param bool $doNotMake
     * @return SearchEngineDataObject|null
     */
    public static function find_or_make($obj, $doNotMake = false)
    {
        if ($obj->hasExtension(SearchEngineMakeSearchable::class)) {
            if ($obj->SearchEngineExcludeFromIndex()) {
                return null;
            }
            $fieldArray = [
                'DataObjectClassName' => $obj->ClassName,
                'DataObjectID' => $obj->ID,
            ];
            $item = DataObject::get_one(SearchEngineDataObject::class, $fieldArray);
            if ($item || $doNotMake) {
                //do nothing;
            } else {
                $item = SearchEngineDataObject::create($fieldArray);
                $item->write();
            }

            return $item;
        }
        user_error('Object does not have expected extension: '.SearchEngineMakeSearchable::class.'.');
    }

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

    /**
     * returns it like this:
     *
     *     Page => General Page
     *     HomePage => Home Page
     *
     * @return array
     */
    public static function searchable_class_names(): array
    {
        if (count(self::$_searchable_class_names) === 0) {
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
            if (is_array($include) && count($include)) {
                foreach ($include as $includeOne) {
                    $includeClassNames = array_merge($includeClassNames, ClassInfo::subclassesFor($includeOne));
                }
            }
            $includeClassNames = array_unique($includeClassNames);

            //if we have inclusions then this is the final list
            if (count($includeClassNames)) {
                $testArray = $includeClassNames;
            } else {
                //lets see which ones are excluded from full list.
                $testArray = $allClasses;
                $exclude = Config::inst()->get(SearchEngineDataObject::class, 'classes_to_exclude');
                if (is_array($exclude) && count($exclude)) {
                    foreach ($exclude as $excludeOne) {
                        $excludeClassNames = array_merge($excludeClassNames, ClassInfo::subclassesFor($excludeOne));
                    }
                }
                $excludeClassNames = array_unique($excludeClassNames);
                if (count($excludeClassNames)) {
                    foreach ($excludeClassNames as $excludeOne) {
                        unset($testArray[$excludeOne]);
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
     * @param DataObject $obj
     */
    public static function remove($obj)
    {
        $item = self::find_or_make($obj, $doNotMake = true);
        if ($item && $item->exists()) {
            $item->delete();
        }
    }

    public static function fields_for_indexing($sourceObject)
    {
        $className = $sourceObject->getKey(true);
        if (! isset(self::$_search_engine_fields_for_indexing[$className])) {
            $levelFields = [
                1 => [],
                2 => [],
            ];
            if ($sourceObject) {
                $levelFields = Config::inst()->get($sourceObject->ClassName, 'search_engine_full_contents_fields_array');
                if (is_array($levelFields) && count($levelFields)) {
                    //do nothing
                } else {
                    $levelOneFieldArray = Config::inst()->get(self::class, 'search_engine_default_level_one_fields');
                    $excludedFieldArray = Config::inst()->get(self::class, 'search_engine_default_excluded_db_fields');
                    $dbArray = SearchEngineMakeSearchableApi::search_engine_rel_fields($sourceObject, 'db');
                    $levelFields = [SearchEngineKeyword::level_sanitizer(1) => [], SearchEngineKeyword::level_sanitizer(2) => []];
                    foreach ($dbArray as $field => $type) {
                        //get without brackets ...
                        if (preg_match('/^(\w+)\(/', $type, $match)) {
                            $type = $match[1];
                        }
                        if (is_subclass_of($type, DBString::class)) {
                            if (in_array($field, $excludedFieldArray, true)) {
                                //do nothing
                            } else {
                                $level = 2;
                                if (in_array($field, $levelOneFieldArray, true)) {
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

    public static function content_for_index_building(DataObject $sourceObject) : array
    {
        $finalArray = [];
        if ($sourceObject) {
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
                                $finalArray[$level] .= ' ' . SearchEngineMakeSearchableApi::make_searchable_rel_object($sourceObject, $fields) . ' ';
                            }
                        }
                    }
                }
            }
        }

        return $finalArray;
    }

}
