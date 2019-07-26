<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Api\SearchEngineDataObjectApi;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;

class SearchEngineSetSortDate extends SearchEngineBaseTask
{
    /**
     * title of the task
     * @var string
     */
    protected $title = 'Update Sort Date for all Search Engine Data Objects';

    /**
     * title of the task
     * @var string
     */
    protected $description = 'Goes through all Search Engine Objects and updates the Date based on the Source Object';

    /**
     * Set a custom url segment (to follow dev/tasks/)
     *
     * @config
     * @var string
     */
    private static $segment = 'searchenginesetsortdate';

    /**
     * this function runs the SearchEngineUpdateSearchIndex task
     * @param SS_HTTPRequest | null $request
     */
    public function run($request)
    {
        $this->runStart($request);
        SearchEngineDataObjectApi::start_indexing_mode();

        $count = SearchEngineDataObject::get()->count();
        $sort = null;
        if ($count > $this->limit) {
            $count = $this->limit;
            $sort = DB::get_conn()->random() . ' ASC';
        }
        for ($i = 0; $i <= $count; $i += $this->step) {
            $timeStart = microtime(true);
            $objects = SearchEngineDataObject::get()
                ->limit($this->step, $i);
            if ($sort) {
                $objects = $objects->sort($sort);
            }
            foreach ($objects as $object) {
                $object->DataObjectDate = $object->SearchEngineSourceObjectSortDate();
                $object->write();
                $this->flushNow($object->DataObjectDate . ' - ' . $object->getTitle());
            }
            $timeEnd = microtime(true);
            $this->flushNow('Time taken: ' . round(($timeEnd - $timeStart), 2));
        }
        SearchEngineDataObjectApi::end_indexing_mode();

        $this->runEnd($request);
    }
}
