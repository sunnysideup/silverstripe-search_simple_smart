<?php

namespace Sunnysideup\SearchSimpleSmart\Api;
use SilverStripe\ORM\DataList;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

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
     * @var int
     */
    private static $acceptable_max_number_of_ranges = 50;

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
     * @param string  $className
     * @param array   $idList
     * @param string  $field
     * @param boolean $isNumber
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


    protected function turnRangeIntoWhereStatement(array $idList) : ?array
    {
        $ranges = $this->findRanges($idList);
        if(count($ranges) === 0) {
            return null;
        }
        $finalArray = [];
        foreach($ranges as $range) {
            $min = min($range);
            $max = max($range);
            $finalArray[] = '"'.$this->className.'"."'.$this->field.'" BETWEEN '.$min.' AND '.$max;
        }
        return implode(' OR ', $finalArray);
    }


    protected function findRanges(array $idList) : array
    {
        $ranges = [];
        $lastOne = 0;
        $currentRangeKey = 0;
        sort($idList);
        foreach($idList as $key => $id){
            if($id === ($lastOne + 1)) {
                // do nothing
            } else {
                $currentRangeKey++;
            }
            $ranges[$currentRangeKey][] = $id;
        }
        return $ranges;
    }

}
