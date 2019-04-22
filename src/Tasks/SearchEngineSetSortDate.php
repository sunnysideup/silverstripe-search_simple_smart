<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use Sunnysideup\SearchSimpleSmart\Extensions\SearchEngineMakeSearchable;
use SilverStripe\ORM\DB;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\Environment;

class SearchEngineSetSortDate extends SearchEngineBaseTask
{
    /**
     * Set a custom url segment (to follow dev/tasks/)
     *
     * @config
     * @var string
     */
    private static $segment = 'searchenginesetsortdate';

    /**
     * title of the task
     * @var string
     */
    protected $title = "Update Sort Date for all Search Engine Data Objects";

    /**
     * title of the task
     * @var string
     */
    protected $description = "Goes through all Search Engine Objects and updates the Date based on the Source Object";

    /**
     * this function runs the SearchEngineUpdateSearchIndex task
     * @param SS_HTTPRequest | null $request
     */
    public function run($request)
    {

        $this->runStart($request);
        SearchEngineDataObject::start_indexing_mode();

        $count = SearchEngineDataObject::get()->count();
        if($count > $this->limit) {
            $count = $this->limit;
            $sort = DB::get_conn()->random().' ASC';
        }
        for ($i = 0; $i <= $count; $i = $i + $this->step) {
            $timeStart = microtime(true);
            $objects = SearchEngineDataObject::get()->limit($this->step, $i);
            foreach($objects as $object) {
                $object->DataObjectDate = $object->SearchEngineSourceObjectSortDate();
                $object->write();
                $this->flushNow($object->DataObjectDate.' - '.$object->getTitle());
            }
            $timeEnd = microtime(true);
            $this->flushNow('Time taken: '.round(($timeEnd - $timeStart), 2));
        }
        SearchEngineDataObject::end_indexing_mode();

        $this->runEnd($request);

    }



}
