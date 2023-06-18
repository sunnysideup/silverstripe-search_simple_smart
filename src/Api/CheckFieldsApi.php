<?php

namespace Sunnysideup\SearchSimpleSmart\Api;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBString;
use SilverStripe\Security\LoginAttempt;
use SilverStripe\Security\MemberPassword;
use SilverStripe\Security\RememberLoginHash;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\ChangeSetItem;

use SilverStripe\SessionManager\Models\LoginSession;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SiteWideSearch\Helpers\Cache;

class CheckFieldsApi
{
    use Extensible;
    use Configurable;
    use Injectable;

    /**
     * @var string
     */
    private const CACHE_NAME = 'checkFieldsCache';

    protected $debug = false;

    protected $baseClass = DataObject::class;

    protected $excludedClasses = [];

    protected $excludedFields = [];

    protected $excludedClassFieldCombos = [];

    protected $cache = [];

    private static $default_exclude_classes = [
        MemberPassword::class,
        LoginAttempt::class,
        ChangeSet::class,
        ChangeSetItem::class,
        RememberLoginHash::class,
        LoginSession::class,
    ];

    private static $default_exclude_fields = [

    ];


    private static $default_exclude_class_field_combos = [
        Member::class => [
            'ID',
        ]
    ];

    public function setDebug(bool $b): CheckFieldsApi
    {
        $this->debug = $b;

        return $this;
    }

    public function setBaseClass(string $class): CheckFieldsApi
    {
        $this->baseClass = $class;

        return $this;
    }

    public function setExcludedClasses(array $a): CheckFieldsApi
    {
        $this->excludedClasses = $a;

        return $this;
    }


    public function setExcludedFields(array $a): CheckFieldsApi
    {
        $this->excludedFields = $a;

        return $this;
    }

    public function setExcludedClassFieldCombos(array $a): CheckFieldsApi
    {
        $this->excludedClassFieldCombos = $a;

        return $this;
    }


    protected function getFileCache()
    {
        return Injector::inst()->get(Cache::class);
    }

    protected function initCache(): self
    {
        $this->cache = $this->getFileCache()->getCacheValues(self::CACHE_NAME);

        return $this;
    }

    protected function saveCache(): self
    {
        $this->getFileCache()->setCacheValues(self::CACHE_NAME, $this->cache);

        return $this;
    }



    public function getList(?string $word = ''): array
    {
        $this->initCache();

        $this->workOutExclusions();

        foreach ($this->getAllDataObjects() as $className) {
            if ($this->debug) {
                DB::alteration_message(' ... Searching in ' . $className);
            }

            if (! in_array($className, $this->excludedClasses, true)) {
                $fields = $this->getAllValidFields($className);

            }

            if ($this->debug) {
                DB::alteration_message(' ... Skipping ' . $className);
            }
        }
        $this->removeDoubles();

        $this->saveCache();
        return $this->cache;


    }


    protected function workOutExclusions()
    {
        $this->excludedClasses = array_unique(
            array_merge(
                $this->Config()->get('default_exclude_classes'),
                $this->excludedClasses
            )
        );
        $this->excludedFields = array_unique(
            array_merge(
                Config::inst()->get(SearchEngineDataObject::class, 'search_engine_default_excluded_db_fields'),
                $this->excludedFields
            )
        );
        $this->excludedClassFieldCombos =
            (array) array_merge_recursive(
                (array) $this->Config()->get('default_exclude_class_field_combos'),
                (array) $this->excludedClassFieldCombos
            );
    }


    protected function getAllDataObjects(): array
    {
        if ($this->debug) {
            DB::alteration_message('Base Class: ' . $this->baseClass);
        }

        if (! isset($this->cache['AllDataObjects'][$this->baseClass])) {
            $this->cache['AllDataObjects'][$this->baseClass] = array_values(
                ClassInfo::subclassesFor($this->baseClass, false)
            );
            $this->cache['AllDataObjects'][$this->baseClass] = array_unique($this->cache['AllDataObjects'][$this->baseClass]);
        }
        return $this->cache['AllDataObjects'][$this->baseClass];
    }

    protected function getAllValidFields(string $className): array
    {

        if (! isset($this->cache['AllValidFields'][$className])) {
            $singleton = Injector::inst()->get($className);
            if($singleton->hasMethod('Link') || $singleton->hasMethod('getLink')) {
                $this->cache['AllValidFields'][$className]['IsBaseClass'] = $this->isBaseClass($className);
                $array = [];
                $arrayIndexed = [];
                $dbFields = Config::inst()->get($className, 'db');
                if (is_array($dbFields)) {
                    foreach ($dbFields as $name => $type) {
                        if (in_array($name, $this->excludedFields, true)) {
                            continue;
                        }
                        if (!$this->isValidFieldType($className, $name, $type)) {
                            continue;
                        }
                        if(in_array($name, $this->excludedClassFieldCombos[$className]?? [])) {
                            continue;
                        }

                        $array[] = $name;
                    }

                    $arrayIndexed = $this->getIndexedFields(
                        $className,
                        $array,
                    );
                }
                $array = array_diff($array, $arrayIndexed);
                $rels = (array)
                    (array)Config::inst()->get($className, 'belongs') +
                    (array)Config::inst()->get($className, 'has_one') +
                    (array)Config::inst()->get($className, 'has_many') +
                    (array)Config::inst()->get($className, 'many_many') +
                    (array)Config::inst()->get($className, 'belongs_many_many');

                foreach($rels as $relName => $relType) {
                    if (in_array($relName, $this->excludedFields, true)) {
                        continue;
                    }
                    if(in_array($relType, $this->excludedClasses)) {
                        continue;
                    }
                    if(in_array($relType, $this->excludedClassFieldCombos[$className]?? [])) {
                        continue;
                    }
                    $hasTitleField = isset($dbFields['Title']) || isset($dbFields['Name']) ;
                    $msg = ($hasTitleField ? '' : '# no title field present in '.$relType .' please add one');
                    $array[] = $relName .'.Title' . $msg;
                }
                $this->cache['AllValidFields'][$className]['Level1'] = $arrayIndexed;
                $this->cache['AllValidFields'][$className]['Level2'] = $array;
            }
        }

        return $this->cache;
    }

    protected function removeDoubles()
    {
        foreach($this->cache['AllValidFields'] as $className => $classData) {
            $ancestors = array_reverse(ClassInfo::ancestry($className));
            $toRemove = [];
            foreach($ancestors as $ancestor) {
                if(isset($this->cache['AllValidFields'][$ancestor])) {
                    if($ancestor === DataObject::class) {
                        break;
                    }
                    if($ancestor === $className) {
                        continue;
                    }
                    $removeData = $this->cache['AllValidFields'][$ancestor];
                    $toRemove = array_merge($toRemove, $removeData['Level1'], $removeData['Level2']);
                }
            }
            for($i = 1; $i < 3; $i++) {
                $level = 'Level'.$i;
                foreach($classData[$level] as $key => $field) {
                    if(in_array($field, $toRemove)) {
                        $pos = array_search($field, $this->cache['AllValidFields'][$className][$level]);
                        unset($this->cache['AllValidFields'][$className][$level][$key], $pos);
                    }
                }
            }
        }
    }

    protected function getIndexedFields(string $className, array $possibleFields): array
    {
        if (! isset($this->cache['IndexedFields'][$className])) {
            $this->cache['IndexedFields'][$className] = [];
            $indexes = Config::inst()->get($className, 'indexes');
            foreach(Config::inst()->get(SearchEngineDataObject::class, 'search_engine_default_level_one_fields') as $field) {
                $indexes[$field] = true;
            }
            if (is_array($indexes)) {
                foreach ($indexes as $key => $field) {
                    if (in_array($key, $possibleFields)) {
                        $this->cache['IndexedFields'][$className][$key] = $key;
                    } elseif (is_array($field)) {
                        foreach ($field as $test) {
                            if (is_array($test)) {
                                if (isset($test['columns'])) {
                                    $test = $test['columns'];
                                } else {
                                    continue;
                                }
                            }

                            $testArray = explode(',', $test);
                            foreach ($testArray as $testInner) {
                                $testInner = trim($testInner);
                                if (in_array($testInner, $possibleFields)) {
                                    $this->cache['IndexedFields'][$className][$testInner] = $testInner;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $this->cache['IndexedFields'][$className];
    }

    protected function isValidFieldType(string $className, string $fieldName, string $type): bool
    {
        if (! isset($this->cache['ValidFieldTypes'][$type])) {
            $this->cache['ValidFieldTypes'][$type] = false;
            $singleton = Injector::inst()->get($className);
            $field = $singleton->dbObject($fieldName);
            if ($field instanceof DBString) {
                $this->cache['ValidFieldTypes'][$type] = true;
            }
        }

        return $this->cache['ValidFieldTypes'][$type];
    }

    protected function isBaseClass(string $className): bool
    {
        $ancestors = array_reverse(ClassInfo::ancestry($className));
        foreach(array_values($ancestors) as $key => $className) {
            if($key === 1 && $className === DataObject::class) {
                return true;
            }
        }
        return false;
    }
}
