<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use Sunnysideup\SearchSimpleSmart\Extensions\SearchEngineMakeSearchable;
use SilverStripe\ORM\DB;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\Environment;

class SearchEngineUpdateSearchIndex extends SearchEngineBaseTask
{
    /**
     * Set a custom url segment (to follow dev/tasks/)
     *
     * @config
     * @var string
     */
    private static $segment = 'searchengineupdatesearchindex';

    /**
     * title of the task
     * @var string
     */
    protected $title = "Update Search Index";

    /**
     * title of the task
     * @var string
     */
    protected $description = "Updates all the search indexes. Boolean GET parameter available: ?oldonesonly";

    /**
     * this function runs the SearchEngineUpdateSearchIndex task
     * @param SS_HTTPRequest | null $request
     */
    public function run($request)
    {

        $this->runStart($request);
        SearchEngineDataObject::start_indexing_mode();

        $count = SearchEngineDataObjectToBeIndexed::get()->count();
        if($count > $this->limit) {
            $count = $this->limit;
            $sort = DB::get_conn()->random().' ASC';
        }
        for ($i = 0; $i <= $count; $i = $i + $this->step) {
            $timeStart = microtime(true);
            $searchEngineDataObjectsToBeIndexed = SearchEngineDataObjectToBeIndexed::to_run($this->oldOnesOnly, $this->step);
            if($searchEngineDataObjectsToBeIndexed->count() === 0) {
                break;
            }
            $this->flushNow('Running '.$this->step.' records of '.$count.', startiong from position '.$i);
            foreach ($searchEngineDataObjectsToBeIndexed as $searchEngineDataObjectToBeIndexed) {
                $searchEngineDataObject = $searchEngineDataObjectToBeIndexed->SearchEngineDataObject();
                if ($searchEngineDataObject) {
                    $sourceObject = $searchEngineDataObject->SourceObject();
                    if ($sourceObject) {
                        $this->flushNow("Indexing ".$searchEngineDataObject->DataObjectClassName.".".$searchEngineDataObject->DataObjectID."", "created");
                        $searchEngineDataObject->doSearchEngineIndex(
                            $sourceObject,
                            $withModeChange = false,
                            $timeMeasure = true
                        );
                        foreach($searchEngineDataObject->getTimeMeasure() as $key => $time) {
                            $this->flushNow($key.': '.round($time, 2));
                        }
                    } else {
                        $this->flushNow("Could not find ".$searchEngineDataObject->DataObjectClassName.".".$searchEngineDataObject->DataObjectID." thus deleting entry", "deleted");
                        $searchEngineDataObject->delete();
                    }
                } else {
                    $this->flushNow("Could not find item for: ".$searchEngineDataObjectToBeIndexed->ID, "deleted");
                }
                $searchEngineDataObjectToBeIndexed->Completed = 1;
                $searchEngineDataObjectToBeIndexed->write();
            }
            $timeEnd = microtime(true);
            $this->flushNow('Time taken: '.round(($timeEnd - $timeStart), 2));
        }
        SearchEngineDataObject::end_indexing_mode();

        $this->runEnd($request);

    }



}
