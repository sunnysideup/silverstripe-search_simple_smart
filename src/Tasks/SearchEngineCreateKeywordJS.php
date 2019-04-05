<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use Sunnysideup\SearchSimpleSmart\Api\ExportKeywordList;
use SilverStripe\ORM\DB;
use SilverStripe\Dev\BuildTask;

class SearchEngineCreateKeywordJS extends SearchEngineBaseTask
{



    /**
     * Set a custom url segment (to follow dev/tasks/)
     *
     * @config
     * @var string
     */
    private static $segment = 'searchenginecreatekeywordjs';

    /**
     * title of the task
     * @var string
     */
    protected $title = "Update Keyword Javascript List";

    /**
     * description of the task
     * @var string
     */
    protected $description = "This list is used for the autocomplete function.";


    /**
     * this function runs the SearchEngineRemoveAll task
     * @param var $request
     */
    public function run($request)
    {
        $this->runStart($request);

        $outcome = ExportKeywordList::export_keyword_list();
        DB::alteration_message($outcome, "created");

        $this->runEnd($request);
    }





}
