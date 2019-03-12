<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use Sunnysideup\SearchSimpleSmart\Model\SearchEngineDataObjectToBeIndexed;
use Sunnysideup\SearchSimpleSmart\Extensions\SearchEngineMakeSearchable;
use SilverStripe\ORM\DB;
use SilverStripe\Dev\BuildTask;

class SearchEngineUpdateSearchIndex extends BuildTask
{

    /**
     * title of the task
     * @var string
     */
    protected $title = "Update Search Index";

    /**
     * title of the task
     * @var string
     */
    protected $description = "Updates all the search indexes. Boolean GET parameter available: ?uptonow";

    /**
     * @var boolean
     */
    protected $verbose = true;

    /**
     * @var boolean
     */
    protected $upToNow = false;

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
    public function setUpToNow($b)
    {
        $this->upToNow = $b;
    }

    /**
     * this function runs the SearchEngineUpdateSearchIndex task
     * @param SS_HTTPRequest | null $request
     */
    public function run($request)
    {
        //set basics
        set_time_limit(3600);
        ob_start();
        //evaluate get variables
        if ($request) {
            $this->upToNow = $request->getVar("uptonow") ? true : false;
        }
        //get data
        if ($this->verbose) {
            echo "<h2>Starting</h2>";
        }
        $searchEngineDataObjectsToBeIndexed = SearchEngineDataObjectToBeIndexed::to_run($this->upToNow);
        foreach ($searchEngineDataObjectsToBeIndexed as $searchEngineDataObjectToBeIndexed) {
            $item = $searchEngineDataObjectToBeIndexed->SearchEngineDataObject();
            if ($item) {
                $sourceObject = $item->SourceObject();
                if ($sourceObject) {
                    if ($sourceObject::has_extension(SearchEngineMakeSearchable::class)) {
                        if ($this->verbose) {
                            DB::alteration_message("Indexing ".$item->DataObjectClassName.".".$item->DataObjectID."", "created");
                        }
                        $sourceObject->searchEngineIndex();
                        if ($this->verbose) {
                            flush();
                            ob_end_flush();
                            ob_start();
                        }
                    } else {
                        if ($this->verbose) {
                            DB::alteration_message("Could not find ".$item->DataObjectClassName.".".$item->DataObjectID." thus deleting entry", "deleted");
                        }
                        $item->delete();
                        if ($this->verbose) {
                            flush();
                            ob_end_flush();
                            ob_start();
                        }
                    }
                }
            } else {
                if ($this->verbose) {
                    DB::alteration_message("Could not find item for: ".$searchEngineDataObjectToBeIndexed->ID, "deleted");
                }
            }
            $searchEngineDataObjectToBeIndexed->Completed = 1;
            $searchEngineDataObjectToBeIndexed->write();
        }
        if ($this->verbose) {
            DB::alteration_message("====================== completed =======================");
        }
    }
}
