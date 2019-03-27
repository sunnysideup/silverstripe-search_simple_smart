<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObject;
use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use Sunnysideup\SearchSimpleSmart\Extensions\SearchEngineMakeSearchable;
use SilverStripe\ORM\DB;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\Environment;

class SearchEngineUpdateSearchIndex extends BuildTask
{

    protected $recursions = 100;

    protected $step = 10;

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
     * @var boolean
     */
    protected $verbose = true;

    /**
     * @var boolean
     */
    protected $oldOnesOnly = false;

    /**
     * @param boolean
     */
    public function setVerbose($b)
    {
        $this->verbose = $b;
    }

    /**
     * @param boolean
     */
    public function setOldOnesOnly($b)
    {
        $this->oldOnesOnly = $b;
    }

    /**
     * this function runs the SearchEngineUpdateSearchIndex task
     * @param SS_HTTPRequest | null $request
     */
    public function run($request)
    {
        //set basics
        ini_set('memory_limit', '512M');
        Environment::increaseMemoryLimitTo();
        //20 minutes
        Environment::increaseTimeLimitTo(7200);
        //evaluate get variables
        if ($request) {
            $this->oldOnesOnly = $request->getVar("oldonesonly") ? true : false;
        }
        //get data
        if ($this->verbose) {
            echo "<h2>Starting</h2>";
        }

        SearchEngineDataObject::start_indexing_mode();

        for($i = 0; $i < $this->recursions; $i++ ) {
            $searchEngineDataObjectsToBeIndexed = SearchEngineDataObjectToBeIndexed::to_run($this->oldOnesOnly, $this->step);
            $count = $searchEngineDataObjectsToBeIndexed->count();
            if($count === 0) {
                break;
            }
            $this->flushNow('Recursion '.$i.' with '.$count.' records');
            foreach ($searchEngineDataObjectsToBeIndexed as $searchEngineDataObjectToBeIndexed) {
                $searchEngineDataObject = $searchEngineDataObjectToBeIndexed->SearchEngineDataObject();
                if ($searchEngineDataObject) {
                    $sourceObject = $searchEngineDataObject->SourceObject();
                    if ($sourceObject) {
                        if ($this->verbose) {
                            $this->flushNow("Indexing ".$searchEngineDataObject->DataObjectClassName.".".$searchEngineDataObject->DataObjectID."", "created");
                        }
                        $sourceObject->searchEngineIndex($searchEngineDataObject, false);
                    } else {
                        if ($this->verbose) {
                            $this->flushNow("Could not find ".$searchEngineDataObject->DataObjectClassName.".".$searchEngineDataObject->DataObjectID." thus deleting entry", "deleted");
                        }
                        $searchEngineDataObject->delete();
                    }
                } else {
                    if ($this->verbose) {
                        $this->flushNow("Could not find item for: ".$searchEngineDataObjectToBeIndexed->ID, "deleted");
                    }
                }
                $searchEngineDataObjectToBeIndexed->Completed = 1;
                $searchEngineDataObjectToBeIndexed->write();
            }
        }
        SearchEngineDataObject::end_indexing_mode();
        if ($this->verbose) {
            $this->flushNow("====================== completed =======================");
        }
    }


    public function flushNow($message, $type = '', $bullet = true)
    {
        echo '';
        // check that buffer is actually set before flushing
        if (ob_get_length()) {
            @ob_flush();
            @flush();
            @ob_end_flush();
        }
        @ob_start();
        if ($bullet) {
            DB::alteration_message($message, $type);
        } else {
            echo $message;
        }
    }

}
