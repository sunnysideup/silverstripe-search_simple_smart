<?php

namespace Sunnysideup\SearchSimpleSmart\Api;
use SilverStripe\ORM\DataList;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

/**
 * turns a query statement of select from MyTable where ID IN (1,,2,3.......999999)
 * into something like:
 * - select from MyTable where ID between 0 and 99 or between 200 and 433
 * OR
 * - select from MyTable where ID NOT IN (4543)
 *

 */

class FasterIDLists
{
    use Extensible;
    use Injectable;
    use Configurable;
    /**
     *
     * @var int
     */
    private static $acceptable_max_number_of_ids = 50;

    /**
     *
     * @var array
     */
    protected $idList = [];

    /**
     *
     * @var string
     */
    protected $className = '';

    /**
     *
     * @var bool
     */
    protected $isBetterWithExclude = false;

    /**
     *
     * @var array
     */
    protected $ranges = [];

    /**
     *
     * @var int
     */
    protected $tableRowCount = 0;

    /**
     *
     * @var string
     */
    protected $field = 'ID';

    /**
     *
     * @var bool
     */
    protected $isNumber = true;


    /**
     *
     * @param string  $className class name of Data Object being queried
     * @param array   $idList array of ids (or other field) to be selected from class name
     * @param string  $field usually the ID field, but could be another field
     * @param boolean $isNumber is the field a number type (so that we can do ranges OR something else)
     */
    public function bestSQL(string $className, array $idList, $field = 'ID', $isNumber = true): DataList
    {
        $this->className = $className;
        $this->idList = $idList;
        $this->field = $field;
        $this->isNumber = $isNumber;

        if(count($idList) <= $this->Config()->acceptable_max_number_of_ids) {
            return $className::get()->filter([$this->field => $idList]);
        } else {
            $whereStatement = $this->turnRangeIntoWhereStatement($this->idList);
            if($whereStatement) {
                return $className::get()->where($whereStatement);
            }
        }
        $excludeList = $this->excludeList();
        if($excludeList) {
            return $excludeList;
        } else {
            $whereStatement = $this->turnRangeIntoWhereStatement($this->excludeList);
            if($whereStatement) {
                return $className::get()->where($whereStatement);
            }
        }

        //default status ...
        return $className::get()->filter([$this->field => $idList]);

    }

    /**
     *
     * @param string  $className class name of Data Object being queried
     * @param array   $idList array of ids (or other field) to be selected from class name
     * @param string  $field usually the ID field, but could be another field
     * @param boolean $isNumber is the field a number type (so that we can do ranges OR something else)
     */
    public function bestTurnRangeIntoWhereStatement(string $className, array $idList, $field = 'ID', $isNumber = true): string
    {
        $this->className = $className;
        $this->idList = $idList;
        $this->field = $field;
        $this->isNumber = $isNumber;

        return $this->turnRangeIntoWhereStatement($idList);
    }
    public function turnRangeIntoWhereStatement(array $idList) : ?string
    {
        $ranges = $this->findRanges($idList);
        $otherArray = [];
        if(count($ranges) === 0) {
            return null;
        }
        $finalArray = [];
        foreach($ranges as $range) {
            $min = min($range);
            $max = max($range);
            if($min === $max) {
                $otherArray[$min] = $min;
            } else {
                $finalArray[] = '"'.$this->getTableName().'"."'.$this->field.'" BETWEEN '.$min.' AND '.$max;
            }
        }
        if(count($otherArray)) {
            $finalArray[] = '"'.$this->getTableName().'"."'.$this->field.'" IN('.implode(',', $otherArray).')';
        }
        return '('.implode(') OR (', $finalArray).')';
    }

    protected function excludeList() : ?DataList
    {
        $className = $this->className;
        $idList = $this->idList;
        $countOfList = count($idList);
        $tableCount = $className::get()->count();
        if($countOfList === $tableCount) {
            return $className::get();
        }
        //only run exclude if there is clear win
        if($countOfList > (($tableCount / 2) + ($this->Config()->acceptable_max_number_of_ids / 2))) {
            $this->isBetterWithExclude = true;
            $fullList = $className::get()->column($this->field);
            $this->excludeList = array_diff($fullList, $this->idList);
            if(count($this->excludeList) <= $this->Config()->acceptable_max_number_of_ids) {
                return $className::get()->exclude(['ID' => $this->idList]);
            }
        }
        return null;
    }


    protected function getTableName()
    {
        return Config::inst()->get($this->className, 'table_name');
    }

    /**
     * 0: 3,4,5,6
     * 1: 8,9,10
     * @param  array $idList [description]
     * @return array         [description]
     */
    protected function findRanges(array $idList) : array
    {
        $ranges = [];
        $lastOne = 0;
        $currentRangeKey = 0;
        sort($idList);
        foreach($idList as $key => $id){
            if($id) {
                if(intval($id) === intval($lastOne + 1)) {
                    // do nothing
                } else {
                    $currentRangeKey++;

                }
                if(! isset($ranges[$currentRangeKey])) {
                    $ranges[$currentRangeKey] = [];
                }
                $ranges[$currentRangeKey][$id] = $id;
                $lastOne = $id;
            }
        }
        return $ranges;
    }

}
