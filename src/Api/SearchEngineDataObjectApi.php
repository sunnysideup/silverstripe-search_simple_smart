<?php

namespace Sunnysideup\SearchSimpleSmart\Api;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Sunnysideup\SearchSimpleSmart\Extensions\SearchEngineMakeSearchable;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineSearchRecord;

class SearchEngineDataObjectApi
{
    use Extensible;
    use Injectable;
    use Configurable;

    /**
     * used for caching...
     *
     * @var array
     */
    protected static $_searchable_class_names = [];

    protected static $_original_mode;

    /**
     * @param bool $doNotMake
     */
    public static function find_or_make(DataObject $obj, $doNotMake = false): ?SearchEngineDataObject
    {
        if ($obj->hasExtension(SearchEngineMakeSearchable::class)) {
            if ($obj->SearchEngineExcludeFromIndex()) {
                return null;
            }

            $fieldArray = [
                'DataObjectClassName' => $obj->ClassName,
                'DataObjectID' => $obj->ID,
            ];
            $item = SearchEngineDataObject::get(SearchEngineDataObject::class, $fieldArray)->first();
            if ($item || $doNotMake) {
                //do nothing;
            } else {
                $item = SearchEngineDataObject::create($fieldArray);
                $item->write();
            }

            return $item;
        }

        return null;
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
     * returns it like this:.
     *
     *     Page => General Page
     *     HomePage => Home Page
     */
    public static function searchable_class_names(): array
    {
        if ([] === self::$_searchable_class_names) {
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
            if ([] !== $includeClassNames) {
                $testArray = $includeClassNames;
                $excludeClassNames = Config::inst()->get(SearchEngineDataObject::class, 'classes_to_exclude');
            } else {
                //lets see which ones are excluded from full list.
                $testArray = $allClasses;
                $exclude = Config::inst()->get(SearchEngineDataObject::class, 'classes_to_exclude');
                if (is_array($exclude) && count($exclude)) {
                    foreach ($exclude as $excludeOne) {
                        $excludeClassNames = array_merge($excludeClassNames, ClassInfo::subclassesFor($excludeOne));
                    }
                }

            }
            $excludeClassNames = array_unique($excludeClassNames);
            if ([] !== $excludeClassNames) {
                foreach ($excludeClassNames as $excludeOne) {
                    unset($testArray[$excludeOne]);
                }
            }

            foreach ($testArray as $className) {
                //does it have the extension?
                if ($className::has_extension(SearchEngineMakeSearchable::class)) {
                    $singleton = Injector::inst()->get($className);
                    $dbFields = Config::inst()->get($className, 'db');
                    $hasTitleField = isset($dbFields['Title']) || isset($dbFields['Name']) ;
                    $hasLinkField = $singleton->hasMethod('Link') || $singleton->hasMethod('getLink');
                    $finalClasses[$className] = $singleton->i18n_singular_name() .
                        ($hasLinkField ? '' : ' - ERROR: no link field') .
                        ($hasTitleField ? '' : ' - ERROR: no title field');
                }
            }

            self::$_searchable_class_names = $finalClasses;
        }

        return self::$_searchable_class_names;
    }

    public static function remove(DataObject $obj)
    {
        $item = self::find_or_make($obj, $doNotMake = true);
        if ($item && $item->exists()) {
            $item->delete();
        }
    }
}
