<?php

namespace Sunnysideup\SearchSimpleSmart\Api;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;

class SearchEngineMakeSearchableApi
{
    use Extensible;
    use Injectable;
    use Configurable;

    /**
     * @var array
     */
    protected static $_array_of_relations = [];

    /**
     * @param DataObject $object
     * @param array      $fields array of TWO items.  The first specifies the relation,
     *                           the second one the method that should be run on the relation (if any)
     *                           you can also specific more relations ...
     * @param mixed      $str
     */
    public static function make_searchable_rel_object($object, $fields, $str = ''): string
    {
        if (is_array($fields) && count($fields)) {
            $fieldCount = count($fields);
            $possibleMethod = $fields[0];
            if ('get' === substr((string) $possibleMethod, 0, 3) && $object->hasMethod($possibleMethod) && 1 === $fieldCount) {
                $str .= ' ' . $object->{$possibleMethod}() . ' ';
            } else {
                $dbArray = self::search_engine_rel_fields($object, 'db');
                //db field
                if (isset($dbArray[$fields[0]])) {
                    $dbField = $fields[0];
                    if (1 === $fieldCount) {
                        $str .= ' ' . $object->{$dbField} . ' ';
                    } elseif (2 === $fieldCount) {
                        $method = $fields[1];
                        $str .= ' ' . $object->dbObject($dbField)->{$method}() . ' ';
                    }
                } else {
                    //has one relation
                    $method = array_shift($fields);
                    $hasOneArray = array_merge(
                        self::search_engine_rel_fields($object, 'has_one'),
                        self::search_engine_rel_fields($object, 'belongs_to')
                    );
                    //has_one relation
                    if (isset($hasOneArray[$method])) {
                        $foreignObject = $object->{$method}();
                        $str .= ' ' . self::make_searchable_rel_object($foreignObject, $fields) . ' ';
                    } else {
                        //many relation
                        $manyArray = array_merge(
                            self::search_engine_rel_fields($object, 'has_many'),
                            self::search_engine_rel_fields($object, 'many_many'),
                            self::search_engine_rel_fields($object, 'belongs_many_many')
                        );
                        if (isset($manyArray[$method])) {
                            $foreignObjects = $object->{$method}()->limit(100);
                            foreach ($foreignObjects as $foreignObject) {
                                $str .= ' ' . self::make_searchable_rel_object($foreignObject, $fields) . ' ';
                            }
                        }
                    }
                }
            }
        } else {
            $str .= ' ' . $object->getTitle() . ' ';
        }

        return $str;
    }

    /**
     * returns db, has_one, has_many, many_many, or belongs_many_many fields
     * for object.
     *
     * @param DataObject $object
     * @param string     $relType (db, has_one, has_many, many_many, or belongs_many_many)
     */
    public static function search_engine_rel_fields($object, $relType): array
    {
        if (! isset(self::$_array_of_relations[$object->ClassName])) {
            self::$_array_of_relations[$object->ClassName] = [];
        }

        if (! isset(self::$_array_of_relations[$object->ClassName][$relType])) {
            $value = Config::inst()->get($object->ClassName, $relType);
            if (! is_array($value)) {
                $value = [];
            }

            self::$_array_of_relations[$object->ClassName][$relType] = $value;
        }

        return self::$_array_of_relations[$object->ClassName][$relType];
    }
}
