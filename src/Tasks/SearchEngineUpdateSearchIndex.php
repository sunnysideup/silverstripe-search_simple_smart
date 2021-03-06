<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use SilverStripe\ORM\DB;
use Sunnysideup\SearchSimpleSmart\Api\SearchEngineDataObjectApi;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;

class SearchEngineUpdateSearchIndex extends SearchEngineBaseTask
{
    /**
     * title of the task
     * @var string
     */
    protected $title = 'Update Search Index';

    /**
     * title of the task
     * @var string
     */
    protected $description = 'Updates all the search indexes. Boolean GET parameter available: ?oldonesonly';

    /**
     * Set a custom url segment (to follow dev/tasks/)
     *
     * @config
     * @var string
     */
    private static $segment = 'searchengineupdatesearchindex';

    /**
     * this function runs the SearchEngineUpdateSearchIndex task
     * @param HTTPRequest | null $request
     */
    public function run($request)
    {
        $this->runStart($request);
        SearchEngineDataObjectApi::start_indexing_mode();

        $count = SearchEngineDataObjectToBeIndexed::to_run($this->oldOnesOnly, 99999999)->count();
        $sort = null;
        if ($count > $this->limit) {
            $count = $this->limit;
            $sort = DB::get_conn()->random() . ' ASC';
        }
        for ($i = 0; $i <= $count; $i += $this->step) {
            $timeStart = microtime(true);
            $searchEngineDataObjectsToBeIndexed = SearchEngineDataObjectToBeIndexed::to_run($this->oldOnesOnly, $this->step);
            if ($sort) {
                $searchEngineDataObjectsToBeIndexed = $searchEngineDataObjectsToBeIndexed->sort($sort);
            }
            if ($searchEngineDataObjectsToBeIndexed->count() === 0) {
                break;
            }
            $this->flushNow('Running ' . $this->step . ' records of ' . $count . ', starting from position ' . $i);
            foreach ($searchEngineDataObjectsToBeIndexed as $searchEngineDataObjectToBeIndexed) {
                $searchEngineDataObject = $searchEngineDataObjectToBeIndexed->SearchEngineDataObject();
                if ($searchEngineDataObject) {
                    $sourceObject = $searchEngineDataObject->SourceObject();
                    if ($sourceObject) {
                        $this->flushNow('Indexing ' . $searchEngineDataObject->DataObjectClassName . '.' . $searchEngineDataObject->DataObjectID . '', 'created');
                        $searchEngineDataObject->doSearchEngineIndex(
                            $sourceObject,
                            $withModeChange = false,
                            $timeMeasure = true
                        );
                        foreach ($searchEngineDataObject->getTimeMeasure() as $key => $time) {
                            $this->flushNow($key . ': ' . round($time, 2));
                        }
                    } else {
                        $this->flushNow('Could not find ' . $searchEngineDataObject->DataObjectClassName . '.' . $searchEngineDataObject->DataObjectID . ' thus deleting entry', 'deleted');
                        $searchEngineDataObject->delete();
                    }
                } else {
                    $this->flushNow('Could not find item for: ' . $searchEngineDataObjectToBeIndexed->ID, 'deleted');
                }
                $searchEngineDataObjectToBeIndexed->Completed = 1;
                $searchEngineDataObjectToBeIndexed->write();
            }
            $timeEnd = microtime(true);
            $this->flushNow('Time taken: ' . round(($timeEnd - $timeStart), 2));
        }
        SearchEngineDataObjectApi::end_indexing_mode();

        $this->runEnd($request);
    }
}
