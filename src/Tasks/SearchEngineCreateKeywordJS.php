<?php

namespace Sunnysideup\SearchSimpleSmart\Tasks;

use Sunnysideup\SearchSimpleSmart\Model\SearchEngineKeyword;
use SilverStripe\ORM\DB;
use SilverStripe\Dev\BuildTask;

class SearchEngineCreateKeywordJS extends BuildTask
{



    /**
     * Set a custom url segment (to follow dev/tasks/)
     *
     * @config
     * @var string
     */
    private static $segment = 'SearchEngineCreateKeywordJS';

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
     *
     * @var boolean
     */
    protected $verbose = true;

    /**
     * this function runs the SearchEngineRemoveAll task
     * @param var $request
     */
    public function run($request)
    {
        if ($this->verbose) {
            echo "<h2>Starting</h2>";
        }
        $outcome = SearchEngineKeyword::export_keyword_list();
        if ($this->verbose) {
            DB::alteration_message($outcome, "created");
        }
        if ($this->verbose) {
            echo "<h2>======= DONE ===============</h2>";
        }
    }

    function Link()
    {
        return '/dev/tasks/'.$this->Config()->get('segment');
    }

}
