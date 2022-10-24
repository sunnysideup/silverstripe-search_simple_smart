<?php

namespace Sunnysideup\SearchSimpleSmart\Api;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Flushable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBString;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;

class SearchEngineSourceObjectApi implements Flushable
{


    public static function flush()
    {
        Injector::inst()->get(CacheInterface::class . '.SearchEngine')->clear();
    }

    /**
     * used for caching...
     *
     * @var array
     */
    protected static $_search_engine_fields_for_indexing = [];

    public function SearchEngineSourceObjectSortDate(?DataObject $sourceObject = null)
    {
        if ($sourceObject) {
            if ($sourceObject->hasMethod('SearchEngineSourceObjectSortDate')) {
                return $sourceObject->SearchEngineSourceObjectSortDate();
            }

            $fieldsToCheck = Config::inst()->get(SearchEngineDataObject::class, 'search_engine_date_fields_for_sorting');
            foreach ($fieldsToCheck as $field) {
                if (! empty($sourceObject->{$field})) {
                    return $sourceObject->{$field};
                }
            }
        }
    }

    public function FieldsForIndexing(DataObject $sourceObject): array
    {
        $className = $sourceObject->getSearchEngineKey(true);
        if (! isset(self::$_search_engine_fields_for_indexing[$className])) {
            $levelFields = Config::inst()->get($sourceObject->ClassName, 'search_engine_full_contents_fields_array');
            if (is_array($levelFields) && count($levelFields)) {
                //do nothing
            } else {
                $levelOneFieldArray = Config::inst()->get(SearchEngineDataObject::class, 'search_engine_default_level_one_fields');
                $excludedFieldArray = Config::inst()->get(SearchEngineDataObject::class, 'search_engine_default_excluded_db_fields');
                $dbArray = SearchEngineMakeSearchableApi::search_engine_rel_fields($sourceObject, 'db');
                $levelFields = [SearchEngineKeyword::level_sanitizer(1) => [], SearchEngineKeyword::level_sanitizer(2) => []];
                foreach ($dbArray as $field => $type) {
                    //get without brackets ...
                    if (preg_match('#^(\w+)\(#', $type, $match)) {
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

            self::$_search_engine_fields_for_indexing[$className] = $levelFields;
        }

        return self::$_search_engine_fields_for_indexing[$className];
    }

    public function ContentForIndexBuilding(DataObject $sourceObject): array
    {
        $finalArray = [];
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

        return $finalArray;
    }

    /**
     * returns a template for formatting the object
     * in the search results.
     *
     * @param null|mixed $sourceObject
     * @param mixed      $moreDetails
     */
    public function SearchEngineResultsTemplates($sourceObject = null, $moreDetails = false): array
    {
        $arrayOfTemplates = [];
        if ($sourceObject) {
            if ($sourceObject->hasMethod('SearchEngineResultsTemplatesProvider')) {
                return $sourceObject->SearchEngineResultsTemplatesProvider($moreDetails);
            }

            $template = Config::inst()->get($sourceObject->ClassName, 'search_engine_results_templates');
            if ($template) {
                if ($moreDetails) {
                    return [$template . '_MoreDetails', $template];
                }

                return [$template];
            }

            $parentClasses = class_parents($sourceObject);
            $firstTemplate = 'Sunnysideup\SearchSimpleSmart\Includes\SearchEngineResultItem_' . ClassInfo::shortName($sourceObject->ClassName);
            $arrayOfTemplates = $moreDetails ? [$firstTemplate . '_MoreDetails', $firstTemplate] : [$firstTemplate];
            foreach ($parentClasses as $parent) {
                if (DataObject::class === $parent) {
                    break;
                }
                $parentShort = ClassInfo::shortName($parent);
                if ($moreDetails) {
                    $arrayOfTemplates[] = 'Sunnysideup\SearchSimpleSmart\Includes\SearchEngineResultItem_' . $parentShort . '_MoreDetails';
                }

                $arrayOfTemplates[] = 'Sunnysideup\SearchSimpleSmart\Includes\SearchEngineResultItem_' . $parentShort;
            }

            if ($moreDetails) {
                $arrayOfTemplates[] = 'Sunnysideup\SearchSimpleSmart\Includes\SearchEngineResultItem_DataObject_MoreDetails';
            }

            $arrayOfTemplates[] = 'Sunnysideup\SearchSimpleSmart\Includes\SearchEngineResultItem_DataObject';
        }

        return $arrayOfTemplates;
    }

    public function SearchEngineFieldsToBeIndexedHumanReadable($sourceObject = null, $includeExample = false)
    {
        $str = 'ERROR';
        if ($sourceObject) {
            $levels = $sourceObject->SearchEngineFieldsForIndexing();
            if (is_array($levels)) {
                ksort($levels);
                $fieldLabels = $sourceObject->fieldLabels();
                $str = '<ul>';
                foreach ($levels as $level => $fieldArray) {
                    $str .= '<li><strong>' . $level . '</strong><ul>';
                    foreach ($fieldArray as $field) {
                        $title = isset($fieldLabels[$field]) ? $fieldLabels[$field] . ' [' . $field . ']' : $field;
                        if ($includeExample) {
                            $fields = explode('.', $field);
                            $data = ' ' . SearchEngineMakeSearchableApi::make_searchable_rel_object($sourceObject, $fields) . ' ';
                            $str .= '<li> - <strong>' . $title . '</strong> <em>' . $data . '</em></li>';
                        } else {
                            $str .= '<li> - ' . $title . '</li>';
                        }
                    }

                    $str .= '</ul></li>';
                }

                $str .= '</ul>';
            } else {
                $str = _t('SearchEngineDataObject.NO_FIELDS', '<p>No fields are listed for indexing.</p>');
            }
        }

        return $str;
    }

    /**
     * @param bool  $moreDetails
     * @param mixed $sourceObject
     */
    public function getHTMLOutput($sourceObject, $moreDetails = false): DBField
    {
        if ($sourceObject) {
            $arrayOfTemplates = $sourceObject->SearchEngineResultsTemplates($moreDetails);
            $cacheKey = 'SearchEngine_' . $sourceObject->ClassName . '_' . abs($sourceObject->ID) . '_' . ($moreDetails ? 'MOREDETAILS' : 'NOMOREDETAILS');

            $cache = Injector::inst()->get(CacheInterface::class . '.SearchEngine');

            $templateRender = null;
            if ($cache->has($cacheKey) && 1 === 2) {
                die('ddda');
                $templateRender = $cache->get($cacheKey);
            }

            if ($templateRender) {
                $templateRender = unserialize($templateRender);
            } else {
                $templateRender = $sourceObject->renderWith($arrayOfTemplates);
                $cache->set($cacheKey, serialize($templateRender));
            }

            return $templateRender;
        }

        return DBField::create_field('HTMLText', '');
    }
}
